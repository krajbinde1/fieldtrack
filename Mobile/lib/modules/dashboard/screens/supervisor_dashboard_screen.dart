import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/api/api_client.dart';
import '../../../core/auth/user_role.dart';
import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_metric_card.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_welcome_card.dart';
import '../../../core/widgets/role_shell_widgets.dart';
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

  @override
  Widget build(BuildContext context) {
    final role = widget.auth.userRole;
    final employeeName = widget.auth.session?.employee.fullName.trim() ?? '';
    final name = employeeName.isNotEmpty
        ? employeeName
        : (widget.auth.session?.user.loginId ?? role.label);

    return PgPageScaffold(
      title: 'Param FieldTrack',
      auth: widget.auth,
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          return SupervisorDashboardView(
            name: name,
            role: role,
            data: snapshot.data ?? const {},
            onOpen: (path) => context.push(path),
          );
        },
      ),
    );
  }
}

class SupervisorDashboardView extends StatelessWidget {
  const SupervisorDashboardView({
    super.key,
    required this.name,
    required this.role,
    required this.data,
    required this.onOpen,
  });

  final String name;
  final UserRole role;
  final Map<String, dynamic> data;
  final ValueChanged<String> onOpen;

  String get _prefix =>
      role.isAdmin || role.isDirector ? '/director' : '/manager';

  int _admissionCount(String status) {
    final raw = data['admission_counts'];
    if (raw is Map) {
      final value = raw[status];
      if (value is int) return value;
      if (value is num) return value.toInt();
      return int.tryParse('$value') ?? 0;
    }
    if (status == 'submitted') {
      final fallback = data['admissions'];
      if (fallback is int) return fallback;
      if (fallback is num) return fallback.toInt();
      return int.tryParse('$fallback') ?? 0;
    }
    return 0;
  }

  List<_DashboardModule> get _modules {
    return [
      if (role.isCenterManager)
        _DashboardModule(
          icon: const Icon(Icons.groups_rounded),
          label: 'Users / Employees',
          subtitle: 'Staff in your assigned center(s)',
          path: '/manager/employees',
          color: AppColors.primary,
        ),
      _DashboardModule(
        icon: const Icon(Icons.how_to_reg_rounded),
        label: 'Admissions',
        subtitle: 'Admissions in your scope',
        path: '$_prefix/admissions',
        color: AppColors.info,
      ),
      if (role.isCenterManager || role.isProjectHead)
        _DashboardModule(
          icon: const Icon(Icons.flag_rounded),
          label: 'Admission Targets',
          subtitle: 'Targets for assigned center staff',
          path: '/manager/admission-targets',
          color: AppColors.accent,
        ),
      _DashboardModule(
        icon: const Icon(Icons.event_note_rounded),
        label: 'Leave Requests',
        subtitle: 'Review leave in your scope',
        path: '$_prefix/leaves',
        color: AppColors.warning,
      ),
      _DashboardModule(
        icon: const Icon(Icons.event_available_rounded),
        label: 'Attendance',
        subtitle: 'Daily team attendance',
        path: '$_prefix/team-attendance',
        color: AppColors.primary,
      ),
      _DashboardModule(
        icon: const Icon(Icons.route_rounded),
        label: 'Employee Routes',
        subtitle: 'Field routes for your team',
        path: '$_prefix/route-tracking',
        color: AppColors.secondary,
      ),
      _DashboardModule(
        icon: const Icon(Icons.person_rounded),
        label: 'Profile',
        subtitle: 'Account and password',
        path: '/profile',
        color: AppColors.textSecondary,
      ),
    ];
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        PgWelcomeCard(
          name: name,
          dateLabel: DateFormat('EEEE, d MMM yyyy').format(DateTime.now()),
          role: role.label,
        ),
        const SizedBox(height: AppSpacing.md),
        Wrap(
          spacing: AppSpacing.sm,
          runSpacing: AppSpacing.sm,
          children: [
            _metric('Employees', '${data['employees'] ?? 0}', const Icon(Icons.groups_rounded)),
            _metric('Punched In', '${data['punched_in_today'] ?? 0}', const Icon(Icons.fingerprint_rounded)),
            _metric('Active Routes', '${data['active_routes'] ?? 0}', const Icon(Icons.route_rounded)),
            _metric(
              'Pending Leave',
              '${data['pending_leaves'] ?? 0}',
              const Icon(Icons.event_note_rounded),
              AppColors.amberGradient,
            ),
            _metric(
              'Targets',
              '${data['admission_targets'] ?? 0}',
              const Icon(Icons.flag_rounded),
              AppColors.violetGradient,
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.md),
        Text(
          'Admissions',
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
              ),
        ),
        const SizedBox(height: AppSpacing.sm),
        Wrap(
          spacing: AppSpacing.sm,
          runSpacing: AppSpacing.sm,
          children: [
            for (final item in [
              ('Submitted', 'submitted', AppColors.info),
              ('Confirmed', 'confirmed', AppColors.success),
              ('Draft', 'draft', AppColors.warning),
              ('Reverted', 'reverted', AppColors.accent),
              ('Rejected', 'rejected', AppColors.error),
            ])
              SizedBox(
                width: 104,
                child: Material(
                  color: item.$3.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(14),
                  child: InkWell(
                    onTap: () => onOpen('$_prefix/admissions?status=${item.$2}'),
                    borderRadius: BorderRadius.circular(14),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(10, 10, 10, 8),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${_admissionCount(item.$2)}',
                            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                  fontWeight: FontWeight.w800,
                                  color: item.$3,
                                ),
                          ),
                          Text(
                            item.$1,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: AppSpacing.lg),
        Text(
          'Modules',
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
              ),
        ),
        const SizedBox(height: AppSpacing.sm),
        ..._modules.map(
          (module) => Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: ModuleTile(
              icon: module.icon,
              label: module.label,
              subtitle: module.subtitle,
              iconColor: module.color,
              onTap: () => onOpen(module.path),
            ),
          ),
        ),
      ],
    );
  }

  Widget _metric(
    String title,
    String value,
    Widget icon, [
    List<Color> gradient = AppColors.tealGradient,
  ]) {
    return SizedBox(
      width: 160,
      child: PgMetricCard(
        title: title,
        value: value,
        icon: icon,
        gradient: gradient,
        expand: false,
      ),
    );
  }
}

class _DashboardModule {
  const _DashboardModule({
    required this.icon,
    required this.label,
    required this.subtitle,
    required this.path,
    required this.color,
  });

  final Widget icon;
  final String label;
  final String subtitle;
  final String path;
  final Color color;
}
