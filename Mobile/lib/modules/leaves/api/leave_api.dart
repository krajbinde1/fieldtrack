import 'dart:io';

import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/api/api_dio.dart';
import '../../../core/api/api_errors.dart';
import '../models/leave.dart';

class LeaveApiException implements Exception {
  const LeaveApiException(this.message);
  final String message;
  @override
  String toString() => message;
}

class LeaveApi {
  LeaveApi(this._dio);
  final Dio _dio;

  static Future<LeaveApi> create() async {
    final prefs = await SharedPreferences.getInstance();
    final dio = ApiDio.create(logTag: 'Leave API');
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
    return LeaveApi(dio);
  }

  Future<List<LeaveRecord>> mine() async {
    final data = await _get('/leaves');
    return _list(data);
  }

  Future<LeaveRecord> show(int id) async {
    final data = await _get('/leaves/$id');
    return LeaveRecord.fromJson(_map(data));
  }

  Future<LeaveRecord> apply(Map<String, dynamic> fields, {String? filePath}) async {
    try {
      final response = await _dio.post(
        '/leaves',
        data: await _form(fields, filePath),
      );
      return LeaveRecord.fromJson(_map(response.data['data']));
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<LeaveRecord> update(
    int id,
    Map<String, dynamic> fields, {
    String? filePath,
  }) async {
    try {
      final response = await _dio.patch('/leaves/$id', data: fields);
      var record = LeaveRecord.fromJson(_map(response.data['data']));
      if (filePath != null && filePath.isNotEmpty) {
        final form = FormData.fromMap({
          'document': await MultipartFile.fromFile(
            filePath,
            filename: File(filePath).uri.pathSegments.last,
          ),
        });
        final uploaded = await _dio.post('/leaves/$id/document', data: form);
        record = LeaveRecord.fromJson(_map(uploaded.data['data']));
      }
      return record;
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<void> cancel(int id) async {
    try {
      await _dio.delete('/leaves/$id');
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<List<int>> download(int id) async {
    try {
      final response = await _dio.get<List<int>>(
        '/leaves/$id/document',
        options: Options(responseType: ResponseType.bytes),
      );
      return response.data ?? const <int>[];
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<List<LeaveRecord>> supervisorList(
    String prefix, {
    int? centerId,
    String? status,
  }) async {
    final data = await _get(
      '/$prefix/leaves',
      query: {
        'center_id': ?centerId,
        if (status != null && status.isNotEmpty) 'status': status,
      },
    );
    return _list(data);
  }

  Future<LeaveRecord> supervisorShow(String prefix, int id) async {
    final data = await _get('/$prefix/leaves/$id');
    return LeaveRecord.fromJson(_map(data));
  }

  Future<LeaveRecord> approve(
    int id, {
    String? remark,
    String prefix = 'manager',
  }) async {
    try {
      final response = await _dio.post(
        '/$prefix/leaves/$id/approve',
        data: {'approval_remark': remark},
      );
      return LeaveRecord.fromJson(_map(response.data['data']));
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<LeaveRecord> reject(
    int id,
    String remark, {
    String prefix = 'manager',
  }) async {
    try {
      final response = await _dio.post(
        '/$prefix/leaves/$id/reject',
        data: {'rejection_remark': remark},
      );
      return LeaveRecord.fromJson(_map(response.data['data']));
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<List<int>> supervisorDownload(String prefix, int id) async {
    try {
      final response = await _dio.get<List<int>>(
        '/$prefix/leaves/$id/document',
        options: Options(responseType: ResponseType.bytes),
      );
      return response.data ?? const <int>[];
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<FormData> _form(Map<String, dynamic> fields, String? filePath) async {
    final map = Map<String, dynamic>.from(fields);
    if (filePath != null && filePath.isNotEmpty) {
      map['document'] = await MultipartFile.fromFile(
        filePath,
        filename: File(filePath).uri.pathSegments.last,
      );
    }
    return FormData.fromMap(map);
  }

  List<LeaveRecord> _list(Object? data) {
    final items = data is List ? data : const <dynamic>[];
    return items
        .whereType<Map>()
        .map((item) => LeaveRecord.fromJson(Map<String, dynamic>.from(item)))
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

  LeaveApiException _error(DioException error) {
    if (isConnectionFailure(error)) {
      return LeaveApiException(connectionFailureMessage());
    }
    return LeaveApiException(errorMessage(error));
  }
}
