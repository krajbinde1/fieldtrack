import 'dart:io';

import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/api/api_dio.dart';
import '../../../core/api/api_errors.dart';
import '../models/admission.dart';
import '../models/admission_target.dart';

class AdmissionApiException implements Exception {
  const AdmissionApiException(this.message);
  final String message;
  @override
  String toString() => message;
}

class AdmissionApi {
  AdmissionApi(this._dio);
  final Dio _dio;

  static Future<AdmissionApi> create() async {
    final prefs = await SharedPreferences.getInstance();
    final dio = ApiDio.create(logTag: 'Admissions API');
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
    return AdmissionApi(dio);
  }

  Future<List<NamedLookup>> schemes() async {
    final data = await _get('/admissions/schemes');
    final items = data is List ? data : const <dynamic>[];
    return items
        .whereType<Map>()
        .map((item) => NamedLookup.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }

  Future<AdmissionLookups> lookups() async {
    final data = await _get('/admissions/districts');
    if (data is List) {
      return AdmissionLookups.fromJson({
        'state': 'Maharashtra',
        'districts': data,
      });
    }
    return AdmissionLookups.fromJson(_map(data));
  }

  Future<List<NamedLookup>> talukas(int districtId) async {
    final data = await _get('/admissions/districts/$districtId/talukas');
    final items = data is Map && data['talukas'] is List
        ? data['talukas'] as List
        : data is List
            ? data
            : const <dynamic>[];
    return items
        .whereType<Map>()
        .map((item) => NamedLookup.fromJson(Map<String, dynamic>.from(item)))
        .where((item) => item.id > 0 && item.name.trim().isNotEmpty)
        .toList();
  }

  Future<List<AdmissionRecord>> drafts() => _list('/admissions/drafts');

  Future<List<AdmissionRecord>> submitted() => _list('/admissions/submitted');

  Future<Map<String, int>> mySummary() async {
    final data = await _get('/admissions/summary');
    return _statusCounts(_map(data));
  }

  Future<AdmissionTargetSummary> targetSummary({
    String preset = 'this_week',
  }) async {
    final data = await _get(
      '/admissions/targets/summary',
      query: {'preset': preset},
    );
    return AdmissionTargetSummary.fromJson(_map(data));
  }

  Future<List<AdmissionTargetRecord>> targets() async {
    final data = await _get('/admissions/targets');
    final items = data is List ? data : const <dynamic>[];
    return items
        .whereType<Map>()
        .map(
          (item) =>
              AdmissionTargetRecord.fromJson(Map<String, dynamic>.from(item)),
        )
        .toList();
  }

  Future<AdmissionRecord> show(int id) async {
    final data = await _get('/admissions/$id');
    return AdmissionRecord.fromJson(_map(data));
  }

  Future<List<AdmissionRecord>> supervisorList(
    String prefix, {
    String? status,
  }) =>
      _list(
        '/$prefix/admissions',
        query: {
          if (status != null && status.isNotEmpty) 'status': status,
        },
      );

  Future<Map<String, int>> supervisorSummary(String prefix) async {
    final data = await _get('/$prefix/admissions/summary');
    return _statusCounts(_map(data));
  }

  Future<AdmissionRecord> supervisorShow(String prefix, int id) async {
    final data = await _get('/$prefix/admissions/$id');
    return AdmissionRecord.fromJson(_map(data));
  }

  Future<AdmissionRecord> confirm(int id) =>
      _postRecord('/manager/admissions/$id/confirm');

  Future<AdmissionRecord> revert(int id, String reason) => _postRecord(
        '/manager/admissions/$id/revert',
        data: {'reason': reason},
      );

  Future<AdmissionRecord> reject(int id, String reason) => _postRecord(
        '/manager/admissions/$id/reject',
        data: {'reason': reason},
      );

  Future<List<int>> supervisorDownloadDocument({
    required String prefix,
    required int admissionId,
    required int documentId,
  }) async {
    try {
      final response = await _dio.get<List<int>>(
        '/$prefix/admissions/$admissionId/documents/$documentId',
        options: Options(responseType: ResponseType.bytes),
      );
      return response.data ?? const <int>[];
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<AdmissionRecord> saveDraft(Map<String, dynamic> payload, {int? id}) async {
    try {
      final response = id == null
          ? await _dio.post('/admissions/drafts', data: payload)
          : await _dio.patch('/admissions/drafts/$id', data: payload);
      return AdmissionRecord.fromJson(_map(response.data['data']));
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<void> deleteDraft(int id) async {
    try {
      await _dio.delete('/admissions/drafts/$id');
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<AdmissionRecord> submit(int id) async {
    try {
      final response = await _dio.post('/admissions/$id/submit');
      return AdmissionRecord.fromJson(_map(response.data['data']));
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<AdmissionRecord> uploadDocument({
    required int admissionId,
    required String documentType,
    required String filePath,
    void Function(int, int)? onProgress,
  }) async {
    try {
      final form = FormData.fromMap({
        'document_type': documentType,
        'file': await MultipartFile.fromFile(
          filePath,
          filename: File(filePath).uri.pathSegments.last,
        ),
      });
      final response = await _dio.post(
        '/admissions/$admissionId/documents',
        data: form,
        onSendProgress: onProgress,
      );
      final data = response.data['data'];
      if (data is Map && data['admission'] is Map) {
        return AdmissionRecord.fromJson(_map(data['admission']));
      }
      return show(admissionId);
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<AdmissionRecord> removeDocument({
    required int admissionId,
    required int documentId,
  }) async {
    try {
      final response = await _dio.delete(
        '/admissions/$admissionId/documents/$documentId',
      );
      return AdmissionRecord.fromJson(_map(response.data['data']));
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<List<int>> downloadDocument({
    required int admissionId,
    required int documentId,
  }) async {
    try {
      final response = await _dio.get<List<int>>(
        '/admissions/$admissionId/documents/$documentId',
        options: Options(responseType: ResponseType.bytes),
      );
      return response.data ?? const <int>[];
    } on DioException catch (error) {
      throw _error(error);
    }
  }

  Future<List<AdmissionRecord>> _list(
    String path, {
    Map<String, dynamic>? query,
  }) async {
    final data = await _get(path, query: query);
    final items = data is List ? data : const <dynamic>[];
    return items
        .whereType<Map>()
        .map((item) => AdmissionRecord.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }

  Future<AdmissionRecord> _postRecord(
    String path, {
    Map<String, dynamic>? data,
  }) async {
    try {
      final response = await _dio.post(path, data: data);
      return AdmissionRecord.fromJson(_map(response.data['data']));
    } on DioException catch (error) {
      throw _error(error);
    }
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

  Map<String, int> _statusCounts(Map<String, dynamic> map) {
    int count(String key) {
      final value = map[key];
      if (value is int) return value;
      if (value is num) return value.toInt();
      return int.tryParse('$value') ?? 0;
    }

    return {
      'submitted': count('submitted'),
      'confirmed': count('confirmed'),
      'draft': count('draft'),
      'reverted': count('reverted'),
      'rejected': count('rejected'),
      'total': map.containsKey('total')
          ? count('total')
          : count('submitted') +
              count('confirmed') +
              count('draft') +
              count('reverted') +
              count('rejected'),
    };
  }

  AdmissionApiException _error(DioException error) {
    if (isConnectionFailure(error)) {
      return AdmissionApiException(connectionFailureMessage());
    }
    return AdmissionApiException(errorMessage(error));
  }
}
