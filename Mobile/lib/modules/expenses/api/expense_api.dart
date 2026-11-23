import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/api/api_dio.dart';

class ExpenseApiException implements Exception {
  const ExpenseApiException(this.message);
  final String message;
  @override
  String toString() => message;
}

class ExpenseSummary {
  const ExpenseSummary({
    required this.totalSubmitted,
    required this.pendingApproval,
    required this.approved,
    required this.paid,
  });

  final String totalSubmitted;
  final String pendingApproval;
  final String approved;
  final String paid;

  factory ExpenseSummary.fromJson(Map<String, dynamic> json) {
    return ExpenseSummary(
      totalSubmitted: '${json['total_submitted'] ?? '0.00'}',
      pendingApproval: '${json['pending_approval'] ?? '0.00'}',
      approved: '${json['approved'] ?? '0.00'}',
      paid: '${json['paid'] ?? '0.00'}',
    );
  }
}

class ExpenseRecord {
  const ExpenseRecord(this.json);
  final Map<String, dynamic> json;

  int get id => int.tryParse('${json['id']}') ?? 0;
  String get number => '${json['claim_number'] ?? ''}';
  String get status => '${json['status'] ?? ''}';
  String get statusLabel => '${json['status_label'] ?? status}';
  String get paymentStatus => '${json['payment_status'] ?? ''}';
  String get amount => '${json['total_amount'] ?? '0.00'}';
  String get employeeName => '${json['employee_name'] ?? ''}';
  String get centerName => '${json['center_name'] ?? ''}';
  String get rejection => '${json['rejection_remarks'] ?? ''}';
  String get pendingWith => '${json['pending_with'] ?? ''}'.trim();
  List<Map<String, dynamic>> get items => (json['items'] as List? ?? const [])
      .whereType<Map>()
      .map((item) => Map<String, dynamic>.from(item))
      .toList();
  List<Map<String, dynamic>> get approvals =>
      (json['approvals'] as List? ?? const [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList();
  Map<String, dynamic>? get payment => json['payment'] is Map
      ? Map<String, dynamic>.from(json['payment'] as Map)
      : null;

  Map<String, dynamic>? get timeline {
    final value = json['timeline'];
    return value is Map ? Map<String, dynamic>.from(value) : null;
  }

  bool get timelineVisible => timeline?['visible'] == true;

  String? get timelinePendingText {
    final text = '${timeline?['pending_text'] ?? ''}'.trim();
    return text.isEmpty ? null : text;
  }

  List<Map<String, dynamic>> get timelineStages =>
      (timeline?['stages'] as List? ?? const [])
          .whereType<Map>()
          .map((stage) => Map<String, dynamic>.from(stage))
          .toList();
}

class ExpenseQueueResult {
  const ExpenseQueueResult({required this.claims, required this.counts});

  final List<ExpenseRecord> claims;
  final Map<String, int> counts;
}

class ExpenseApi {
  ExpenseApi(this._dio);
  final Dio _dio;

  static Future<ExpenseApi> create() async {
    final prefs = await SharedPreferences.getInstance();
    final dio = ApiDio.create(logTag: 'Expense API');
    dio.options
      ..sendTimeout = const Duration(seconds: 60)
      ..receiveTimeout = const Duration(seconds: 60);
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final token = prefs.getString('login_token') ??
              prefs.getString('token') ??
              '';
          if (token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
      ),
    );
    return ExpenseApi(dio);
  }

  Future<({ExpenseSummary summary, List<ExpenseRecord> claims})> mine() async {
    return _call(() async {
      final body = await _data('/expenses');
      final summary = ExpenseSummary.fromJson(
        body['summary'] is Map
            ? Map<String, dynamic>.from(body['summary'] as Map)
            : const {},
      );
      final claims = (body['claims'] as List? ?? const [])
          .whereType<Map>()
          .map((item) => ExpenseRecord(Map<String, dynamic>.from(item)))
          .toList();
      return (summary: summary, claims: claims);
    });
  }

  Future<ExpenseRecord> show(String prefix, int id) async {
    return _call(() async => ExpenseRecord(await _data('$prefix/$id')));
  }

  Future<ExpenseQueueResult> queue(String prefix, {String status = 'pending'}) async {
    return _call(() async {
      final response = await _dio.get(prefix, queryParameters: {'status': status});
      final data = response.data;
      final rows = data is Map ? data['data'] : null;
      final rawCounts = data is Map && data['counts'] is Map ? data['counts'] as Map : const {};
      return ExpenseQueueResult(
        claims: (rows as List? ?? const [])
            .whereType<Map>()
            .map((item) => ExpenseRecord(Map<String, dynamic>.from(item)))
            .toList(),
        counts: {
          for (final entry in rawCounts.entries)
            '${entry.key}': int.tryParse('${entry.value}') ?? 0,
        },
      );
    });
  }

  Future<ExpenseRecord> save({
    required String action,
    required List<Map<String, dynamic>> items,
    required List<String?> receipts,
    int? id,
    int? centerId,
  }) {
    return _call(() async {
      final form = FormData();
      form.fields.add(MapEntry('action', action));
      form.fields.add(MapEntry('items', jsonEncode(items)));
      if (centerId != null) {
        form.fields.add(MapEntry('center_id', '$centerId'));
      }
      for (var index = 0; index < receipts.length; index++) {
        final path = receipts[index];
        if (path == null || path.isEmpty) continue;
        form.files.add(
          MapEntry(
            'receipts[$index]',
            await MultipartFile.fromFile(
              path,
              filename: path.split(Platform.pathSeparator).last,
            ),
          ),
        );
      }
      final response = id == null
          ? await _dio.post('/expenses', data: form)
          : await _dio.patch('/expenses/$id', data: form);
      return ExpenseRecord(_map(response.data));
    });
  }

  Future<Map<String, dynamic>> selfTravel(String date) {
    return _call(() async {
      final response = await _dio.get('/expenses/self-travel', queryParameters: {'date': date});
      final data = response.data;
      final payload = data is Map ? data['data'] : null;
      return Map<String, dynamic>.from(payload is Map ? payload : const {});
    });
  }

  Future<({String token, String filename})> uploadReceipt(String path) {
    return _call(() async {
      final form = FormData();
      form.files.add(MapEntry(
        'receipt',
        await MultipartFile.fromFile(path, filename: path.split(Platform.pathSeparator).last),
      ));
      final response = await _dio.post('/expenses/receipts', data: form);
      final data = response.data;
      final payload = data is Map ? data['data'] : null;
      final map = Map<String, dynamic>.from(payload is Map ? payload : const {});
      final token = '${map['token'] ?? ''}';
      if (token.isEmpty) {
        throw const ExpenseApiException('Upload failed.');
      }
      return (token: token, filename: '${map['filename'] ?? ''}');
    });
  }

  Future<ExpenseRecord> decide(String prefix, int id, String action, {String? remarks}) {
    return _call(() async {
      final response = await _dio.post('$prefix/$id/$action', data: {
        'remarks': ?remarks,
      });
      return ExpenseRecord(_map(response.data));
    });
  }

  Future<List<int>> downloadReceipt(String prefix, int claimId, int itemId) {
    return _call(() async {
      final response = await _dio.get<List<int>>(
        '$prefix/$claimId/items/$itemId/receipt',
        options: Options(responseType: ResponseType.bytes),
      );
      return response.data ?? const <int>[];
    });
  }

  Future<ExpenseRecord> pay(int id, Map<String, dynamic> payload) {
    return _call(() async {
      final response = await _dio.post('/director/expenses/$id/pay', data: payload);
      return ExpenseRecord(_map(response.data));
    });
  }

  Future<({int unread, List<Map<String, dynamic>> items})> notifications() {
    return _call(() async {
      final response = await _dio.get('/notifications');
      final body = response.data;
      final rows = body is Map ? body['data'] : null;
      return (
        unread: int.tryParse('${body is Map ? body['unread_count'] : 0}') ?? 0,
        items: (rows as List? ?? const [])
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList(),
      );
    });
  }

  Future<void> markRead(int id) async {
    await _dio.post('/notifications/$id/read');
  }

  Future<Map<String, dynamic>> _data(String path) async {
    final response = await _dio.get(path);
    return _map(response.data);
  }

  Future<T> _call<T>(Future<T> Function() action) async {
    try {
      return await action();
    } on ExpenseApiException {
      rethrow;
    } on DioException catch (error) {
      final body = error.response?.data;
      final message = body is Map
          ? '${body['message'] ?? error.message ?? 'Request failed.'}'
          : (error.message ?? 'Request failed.');
      throw ExpenseApiException(message);
    }
  }

  Map<String, dynamic> _map(dynamic body) {
    if (body is Map && body['data'] is Map) {
      return Map<String, dynamic>.from(body['data'] as Map);
    }
    throw ExpenseApiException(
      body is Map ? '${body['message'] ?? 'Could not load expenses.'}' : 'Could not load expenses.',
    );
  }
}
