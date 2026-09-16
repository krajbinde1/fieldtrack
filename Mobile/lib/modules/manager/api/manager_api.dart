import 'package:dio/dio.dart';
import '../../../core/api/api_errors.dart';

class ManagerTeamAttendanceListResult {
  const ManagerTeamAttendanceListResult({
    required this.rows,
    required this.meta,
  });

  final List<Map<String, dynamic>> rows;
  final Map<String, dynamic> meta;

  int get totalEmployees => int.tryParse('${meta['total_employees'] ?? 0}') ?? 0;
  int get punchedIn => int.tryParse('${meta['punched_in'] ?? 0}') ?? 0;
  int get punchedOut => int.tryParse('${meta['punched_out'] ?? 0}') ?? 0;
  int get notPunchedIn => int.tryParse('${meta['not_punched_in'] ?? 0}') ?? 0;
}

class ManagerRouteTrackingListResult {
  const ManagerRouteTrackingListResult({
    required this.rows,
    required this.meta,
  });

  final List<Map<String, dynamic>> rows;
  final Map<String, dynamic> meta;
}

class ManagerEmployeeAttendanceHistoryResult {
  const ManagerEmployeeAttendanceHistoryResult({
    required this.employee,
    required this.rows,
    required this.meta,
  });

  final Map<String, dynamic> employee;
  final List<Map<String, dynamic>> rows;
  final Map<String, dynamic> meta;

  factory ManagerEmployeeAttendanceHistoryResult.fromJson(
    Map<String, dynamic> json,
  ) {
    return ManagerEmployeeAttendanceHistoryResult(
      employee: Map<String, dynamic>.from(json['employee'] as Map? ?? const {}),
      rows: (json['data'] as List?)
              ?.map((item) => Map<String, dynamic>.from(item as Map))
              .toList() ??
          const [],
      meta: Map<String, dynamic>.from(json['meta'] as Map? ?? const {}),
    );
  }
}

class ManagerApi {
  ManagerApi(this._dio);
  final Dio _dio;

  Future<ManagerTeamAttendanceListResult> listTeamAttendance({
    String? date,
    String? search,
  }) async {
    try {
      final response = await _dio.get(
        '/manager/team-attendance',
        queryParameters: {
          'date': ?date,
          if (search != null && search.isNotEmpty) 'search': search,
        },
      );
      final body = response.data as Map;
      final rows = (body['data'] as List?)
              ?.map((item) => Map<String, dynamic>.from(item as Map))
              .toList() ??
          const [];
      final meta = Map<String, dynamic>.from(body['meta'] as Map? ?? const {});
      return ManagerTeamAttendanceListResult(rows: rows, meta: meta);
    } on DioException catch (error) {
      throw mapApiError(error);
    }
  }

  Future<Map<String, dynamic>> getTeamAttendance(int attendanceId) async {
    try {
      final response = await _dio.get('/manager/team-attendance/$attendanceId');
      return Map<String, dynamic>.from(
        (response.data as Map)['data'] as Map,
      );
    } on DioException catch (error) {
      throw mapApiError(error);
    }
  }

  Future<ManagerRouteTrackingListResult> listRouteTracking({
    String? date,
    String? search,
  }) async {
    try {
      final response = await _dio.get(
        '/manager/route-tracking',
        queryParameters: {
          'date': ?date,
          if (search != null && search.isNotEmpty) 'search': search,
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
      return ManagerRouteTrackingListResult(
        rows: rows,
        meta: metaRaw is Map
            ? Map<String, dynamic>.from(metaRaw)
            : <String, dynamic>{},
      );
    } on DioException catch (error) {
      throw mapApiError(error);
    }
  }

  Future<Map<String, dynamic>> getRouteTracking(int attendanceId) async {
    try {
      final response = await _dio.get('/manager/route-tracking/$attendanceId');
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

  Future<ManagerEmployeeAttendanceHistoryResult> getEmployeeAttendanceHistory(
    int employeeId, {
    String? month,
    String? dateFrom,
    String? dateTo,
  }) async {
    try {
      final response = await _dio.get(
        '/manager/team-attendance/employees/$employeeId',
        queryParameters: {
          'month': ?month,
          'date_from': ?dateFrom,
          'date_to': ?dateTo,
        },
      );
      final body = Map<String, dynamic>.from(response.data as Map);
      return ManagerEmployeeAttendanceHistoryResult.fromJson(body);
    } on DioException catch (error) {
      throw mapApiError(error);
    }
  }
}
