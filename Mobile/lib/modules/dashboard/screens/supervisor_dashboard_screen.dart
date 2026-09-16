import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/api/api_client.dart';
import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_metric_card.dart';
import '../../../core/widgets/design/pg_quick_action.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_welcome_card.dart';
import '../../auth/providers/auth_controller.dart';

class SupervisorDashboardScreen extends StatefulWidget {
  const SupervisorDashboardScreen({super.key, required this.auth});
  final AuthController auth;

  @override
  State<SupervisorDashboardScreen> createState() =>
      _SupervisorDashboardScreenState();
}

class _SupervisorDashboardScreenState extends State<SupervisorDashboardScreen> {
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
    final response = await dio.get('/dashboard');
    final body = response.data;
    if (body is Map && body['data'] is Map) {
      return Map<String, dynamic>.from(body['data'] as Map);
    }
    return {};
  }

  @override
  Widget build(BuildContext context) {
    final role = widget.auth.userRole;
    final prefix = role.isAdmin || role.isDirector ? '/director' : '/manager';
    final name = widget.auth.session?.user.loginId ?? role.label;

    return PgPageScaffold(
      title: 'Param FieldTrack',
      auth: widget.auth,
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          final data = snapshot.data ?? {};
          return ListView(
            padding: const EdgeInsets.all(AppSpacing.md),
            children: [
              PgWelcomeCard(
                name: name,
                dateLabel:
                    DateFormat('EEEE, d MMM yyyy').format(DateTime.now()),
                role: role.label,
              ),
              const SizedBox(height: AppSpacing.md),
              Row(
                children: [
                  Expanded(
                    child: PgMetricCard(
                      title: 'Employees',
                      value: '${data['employees'] ?? 0}',
                      icon: const Icon(Icons.groups_rounded),
                      gradient: AppColors.tealGradient,
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: PgMetricCard(
                      title: 'Punched In',
                      value: '${data['punched_in_today'] ?? 0}',
                      icon: const Icon(Icons.fingerprint_rounded),
                      gradient: AppColors.tealGradient,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.sm),
              PgMetricCard(
                title: 'Active Routes',
                value: '${data['active_routes'] ?? 0}',
                icon: const Icon(Icons.route_rounded),
                gradient: AppColors.tealGradient,
              ),
              const SizedBox(height: AppSpacing.lg),
              PgCard(
                child: Row(
                  children: [
                    Expanded(
                      child: PgQuickAction(
                        icon: const Icon(Icons.event_available_rounded),
                        label: 'Attendance',
                        color: AppColors.primary,
                        onTap: () => context.push('$prefix/team-attendance'),
                      ),
                    ),
                    Expanded(
                      child: PgQuickAction(
                        icon: const Icon(Icons.route_rounded),
                        label: 'Routes',
                        color: AppColors.secondary,
                        onTap: () => context.push('$prefix/route-tracking'),
                      ),
                    ),
                    Expanded(
                      child: PgQuickAction(
                        icon: const Icon(Icons.event_note_rounded),
                        label: 'Leave Requests',
                        color: AppColors.info,
                        onTap: () => context.push('$prefix/leaves'),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
