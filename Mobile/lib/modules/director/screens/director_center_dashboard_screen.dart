import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_client.dart';
import '../../../core/auth/user_role.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../auth/providers/auth_controller.dart';
import 'director_dashboard_screen.dart';

class DirectorCenterDashboardScreen extends StatefulWidget {
  const DirectorCenterDashboardScreen({
    super.key,
    required this.auth,
    required this.centerId,
    this.initialCenter,
  });

  final AuthController auth;
  final int centerId;
  final Map<String, dynamic>? initialCenter;

  @override
  State<DirectorCenterDashboardScreen> createState() =>
      _DirectorCenterDashboardScreenState();
}

class _DirectorCenterDashboardScreenState
    extends State<DirectorCenterDashboardScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final dio = ApiClient(
      SessionStore(),
      onUnauthorized: widget.auth.sessionExpired,
    ).dio;
    try {
      final response = await dio.get(
        '/dashboard',
        queryParameters: {'center_id': widget.centerId},
      );
      final body = response.data;
      if (body is Map && body['data'] is Map) {
        return Map<String, dynamic>.from(body['data'] as Map);
      }
    } catch (_) {}
    return const {};
  }

  Future<void> _refresh() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  Future<void> _open(String path) async {
    await context.push(path);
    if (!mounted) return;
    await _refresh();
  }

  Map<String, dynamic> _centerMeta(Map<String, dynamic> data) {
    final nested = data['center'];
    if (nested is Map) {
      return Map<String, dynamic>.from(nested);
    }
    return widget.initialCenter ?? const {};
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Center Dashboard',
      showBack: true,
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          final data = snapshot.data ?? const <String, dynamic>{};
          final center = _centerMeta(data);
          final centerName =
              '${center['name'] ?? widget.initialCenter?['name'] ?? 'Center'}';
          return RefreshIndicator(
            onRefresh: _refresh,
            child: DirectorDashboardView(
              name: widget.auth.session?.displayName ??
                  widget.auth.userRole.label,
              role: widget.auth.userRole.isAdmin
                  ? UserRole.admin
                  : UserRole.director,
              data: data,
              centerId: widget.centerId,
              centerName: centerName,
              schemeName:
                  '${center['scheme_name'] ?? center['project_name'] ?? ''}',
              centerManagerName: '${center['center_manager_name'] ?? ''}',
              onOpen: _open,
            ),
          );
        },
      ),
    );
  }
}
