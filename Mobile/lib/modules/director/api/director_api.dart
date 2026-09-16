import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../../../core/api/api_errors.dart';

class DirectorRouteTrackingListResult {
  const DirectorRouteTrackingListResult({
    required this.rows,
    required this.meta,
  });

  final List<Map<String, dynamic>> rows;
  final Map<String, dynamic> meta;
}

class DirectorApi {
  DirectorApi(this._dio);
  final Dio _dio;

  Future<List<Map<String, dynamic>>> listCenters({String? search}) async {
    try {
      final response = await _dio.get(
        '/director/centers',
        queryParameters: {
          if (search != null && search.isNotEmpty) 'search': search,
        },
      );
      final rows = (response.data as Map)['data'];
      if (rows is! List) return const [];
      return rows
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList();
    } on DioException catch (error) {
      throw mapApiError(error);
    }
  }

  Future<Map<String, dynamic>> getCenter(int centerId) async {
    try {
      final response = await _dio.get('/director/centers/$centerId');
      return Map<String, dynamic>.from(
        (response.data as Map)['data'] as Map? ?? const {},
      );
    } on DioException catch (error) {
      throw mapApiError(error);
    }
  }

  Future<DirectorRouteTrackingListResult> listRouteTracking({
    String? date,
    String? search,
    int? centerId,
  }) async {
    try {
      final response = await _dio.get(
        '/director/route-tracking',
        queryParameters: {
          'date': ?date,
          if (search != null && search.isNotEmpty) 'search': search,
          'center_id': ?centerId,
        },
      );
      final raw = response.data;
      if (raw is! Map) {
        throw StateError('Invalid route tracking response');
      }
      final body = Map<String, dynamic>.from(raw);
      final dataRaw = body['data'];
      final rows = dataRaw is List
          ? dataRaw
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList()
          : <Map<String, dynamic>>[];
      final metaRaw = body['meta'];
      return DirectorRouteTrackingListResult(
        rows: rows,
        meta: metaRaw is Map
            ? Map<String, dynamic>.from(metaRaw)
            : <String, dynamic>{},
      );
    } on DioException catch (error) {
      debugPrint('Director route list error ${error.response?.statusCode}');
      throw mapApiError(error);
    }
  }

  Future<Map<String, dynamic>> getRouteTracking(int attendanceId) async {
    try {
      final response = await _dio.get('/director/route-tracking/$attendanceId');
      final root = response.data;
      if (root is! Map) {
        throw StateError('Invalid route detail response');
      }
      final data = root['data'];
      if (data is! Map) {
        throw StateError('Route detail data missing');
      }
      return Map<String, dynamic>.from(data);
    } on DioException catch (error) {
      throw mapApiError(error);
    }
  }
}
