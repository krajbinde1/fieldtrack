import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_client.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/role_shell_widgets.dart';
import '../../auth/providers/auth_controller.dart';

class ManagerReportsScreen extends StatefulWidget {
  const ManagerReportsScreen({super.key, required this.auth});

  final AuthController auth;

  @override
  State<ManagerReportsScreen> createState() => _ManagerReportsScreenState();
}

class _ManagerReportsScreenState extends State<ManagerReportsScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    try {
      final dio = ApiClient(
        SessionStore(),
        onUnauthorized: widget.auth.sessionExpired,
      ).dio;
      final response = await dio.get('/dashboard');
      final body = response.data;
      if (body is Map && body['data'] is Map) {
        return Map<String, dynamic>.from(body['data'] as Map);
      }
    } catch (_) {}
    return {};
  }

  String _value(Map<String, dynamic> data, String key) => '${data[key] ?? 0}';

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Reports',
      showBack: true,
      body: RefreshIndicator(
        onRefresh: () async {
          final next = _load();
          setState(() => _future = next);
          await next;
        },
        child: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const PgLoadingState();
            }
            final data = snapshot.data ?? const <String, dynamic>{};
            return ListView(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              children: [
                Wrap(
                  spacing: AppSpacing.sm,
                  runSpacing: AppSpacing.sm,
                  children: [
                    DashboardMetricCard(
                      label: 'Employees',
                      value: _value(data, 'employees'),
                      icon: const Icon(Icons.groups_rounded),
                      onTap: () => context.push('/manager/employees'),
                    ),
                    DashboardMetricCard(
                      label: 'Punched In',
                      value: _value(data, 'punched_in_today'),
                      icon: const Icon(Icons.fingerprint_rounded),
                      onTap: () => context.push('/manager/team-attendance'),
                    ),
                    DashboardMetricCard(
                      label: 'Active Routes',
                      value: _value(data, 'active_routes'),
                      icon: const Icon(Icons.route_rounded),
                      onTap: () => context.push('/manager/route-tracking'),
                    ),
                    DashboardMetricCard(
                      label: 'Pending Leave',
                      value: _value(data, 'pending_leaves'),
                      icon: const Icon(Icons.event_note_rounded),
                      onTap: () => context.push('/manager/leaves'),
                    ),
                    DashboardMetricCard(
                      label: 'Targets',
                      value: _value(data, 'admission_targets'),
                      icon: const Icon(Icons.flag_rounded),
                      onTap: () => context.push('/manager/admission-targets'),
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.lg),
                ModuleTile(
                  icon: const Icon(Icons.event_available_rounded),
                  label: 'Attendance report',
                  subtitle: 'Live punch status for assigned centers',
                  onTap: () => context.push('/manager/team-attendance'),
                ),
                const SizedBox(height: AppSpacing.sm),
                ModuleTile(
                  icon: const Icon(Icons.route_rounded),
                  label: 'Route report',
                  subtitle: 'Field routes for assigned staff',
                  onTap: () => context.push('/manager/route-tracking'),
                ),
                const SizedBox(height: AppSpacing.sm),
                ModuleTile(
                  icon: const Icon(Icons.event_note_rounded),
                  label: 'Leave report',
                  subtitle: 'Pending and reviewed leave',
                  onTap: () => context.push('/manager/leaves'),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
