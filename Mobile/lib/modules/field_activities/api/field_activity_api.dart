import 'dart:io';

import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/api/api_dio.dart';
import '../../../core/api/api_errors.dart';
import '../models/field_activity.dart';

class FieldActivityApiException implements Exception {
  const FieldActivityApiException(this.message);
  final String message;
  @override
  String toString() => message;
}

class FieldActivityApi {
  FieldActivityApi(this._dio);
  final Dio _dio;

  static Future<FieldActivityApi> create() async {
    final prefs = await SharedPreferences.getInstance();
    final dio = ApiDio.create(logTag: 'Field Activity API');
    dio.options
      ..sendTimeout = const Duration(seconds: 60)
      ..receiveTimeout = const Duration(seconds: 60);
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final token =
              prefs.getString('login_token') ??
              prefs.getString('token') ??
              const String.fromEnvironment('ATTENDANCE_API_TOKEN');
          if (token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
      ),
    );
    return FieldActivityApi(dio);
  }

  Future<Map<String, String>> types() async {
    final data = await _get('/field-activities/types');
    if (data is Map) {
      return data.map((key, value) => MapEntry('$key', '$value'));
    }
    return const {
      'village_visit': 'Village Visit',
      'community_meeting': 'Community Meeting',
      'awareness_camp': 'Awareness Camp',
      'follow_up': 'Follow-up',
      'household_survey': 'Household Survey',
      'other': 'Other',
    };
  }

  Future<List<FieldActivity>> mine() async {
    final data = await _get('/field-activities');
    return _list(data);
  }

  Future<FieldActivity> show(int id) async {
    final data = await _get('/field-activities/$id');
    return FieldActivity.fromJson(_map(data));
  }

  Future<FieldActivity> submit(Map<String, dynamic> fields, String photoPath) async {
    try {
      final payload = Map<String, dynamic>.from(fields);
      payload['photo'] = await MultipartFile.fromFile(
        photoPath,
        filename: File(photoPath).uri.pathSegments.last,
      );
      final response = await _dio.post(
        '/field-activities',
        data: FormData.fromMap(payload),
      );
      return FieldActivity.fromJson(_map(response.data['data']));
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<FieldActivityListResult> supervisorList(
    String prefix, {
    int? centerId,
    int? employeeId,
    String? period,
    String? dateFrom,
    String? dateTo,
  }) async {
    try {
      final response = await _dio.get(
        '/$prefix/field-activities',
        queryParameters: {
          'center_id': ?centerId,
          'employee_id': ?employeeId,
          if (period != null && period.isNotEmpty) 'period': period,
          if (dateFrom != null && dateFrom.isNotEmpty) 'date_from': dateFrom,
          if (dateTo != null && dateTo.isNotEmpty) 'date_to': dateTo,
        },
      );
      final employees = response.data['meta'] is Map
          ? ((response.data['meta']['employees'] as List?) ?? const [])
          : const [];
      return FieldActivityListResult(
        items: _list(response.data['data']),
        employees: employees
            .whereType<Map>()
            .map(
              (item) => FieldActivityEmployeeOption.fromJson(
                Map<String, dynamic>.from(item),
              ),
            )
            .toList(),
      );
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<FieldActivity> supervisorShow(String prefix, int id) async {
    final data = await _get('/$prefix/field-activities/$id');
    return FieldActivity.fromJson(_map(data));
  }

  List<FieldActivity> _list(Object? data) {
    final items = data is List ? data : const <dynamic>[];
    return items
        .whereType<Map>()
        .map((item) => FieldActivity.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }

  Future<dynamic> _get(String path, {Map<String, dynamic>? query}) async {
    try {
      final response = await _dio.get(path, queryParameters: query);
      return response.data['data'];
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Map<String, dynamic> _map(Object? data) {
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    return <String, dynamic>{};
  }

  FieldActivityApiException _error(DioException error) {
    if (isConnectionFailure(error)) {
      return FieldActivityApiException(connectionFailureMessage());
    }
    return FieldActivityApiException(errorMessage(error));
  }
}
