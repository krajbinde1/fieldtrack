import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_progress_bar.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_welcome_card.dart';
import '../../admissions/api/admission_api.dart';
import '../../admissions/models/admission_target.dart';
import '../../attendance/providers/attendance_provider.dart';
import '../../auth/providers/auth_controller.dart';

class EmployeeDashboardScreen extends ConsumerStatefulWidget {
  const EmployeeDashboardScreen({super.key, required this.auth});

  final AuthController auth;

  @override
  ConsumerState<EmployeeDashboardScreen> createState() =>
      _EmployeeDashboardScreenState();
}

class _EmployeeDashboardScreenState
    extends ConsumerState<EmployeeDashboardScreen> {
  String _preset = 'this_week';
  AdmissionTargetSummary? _summary;
  Object? _error;
  bool _loading = true;
  AdmissionApi? _api;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      _api ??= await AdmissionApi.create();
      final summary = await _api!.targetSummary(preset: _preset);
      if (!mounted) return;
      setState(() {
        _summary = summary;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
      });
    }
  }

  Future<void> _open(String path) async {
    if (path == '/attendance') {
      ref.invalidate(todayAttendanceProvider);
    }
    await context.push(path);
    if (path.startsWith('/admissions') && mounted) {
      await _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final name = widget.auth.session?.employee.fullName ??
        widget.auth.session?.user.loginId ??
        'Employee';

    return PgPageScaffold(
      auth: widget.auth,
      body: SafeArea(
        bottom: false,
        child: EmployeeDashboardView(
          name: name,
          role: widget.auth.userRole.label,
          photoUrl: widget.auth.session?.employee.profilePhotoUrl,
          preset: _preset,
          summary: _summary,
          loading: _loading,
          error: _error,
          onPreset: (value) {
            setState(() => _preset = value);
            _load();
          },
          onRetry: _load,
          onOpen: _open,
        ),
      ),
    );
  }
}

class EmployeeDashboardView extends StatelessWidget {
  const EmployeeDashboardView({
    super.key,
    required this.name,
    required this.role,
    required this.preset,
    required this.loading,
    required this.onPreset,
    required this.onRetry,
    required this.onOpen,
    this.photoUrl,
    this.summary,
    this.error,
  });

  final String name;
  final String role;
  final String? photoUrl;
  final String preset;
  final AdmissionTargetSummary? summary;
  final bool loading;
  final Object? error;
  final ValueChanged<String> onPreset;
  final VoidCallback onRetry;
  final ValueChanged<String> onOpen;

  static const _modules = <_EmployeeModule>[
    _EmployeeModule(
      icon: Icon(Icons.fingerprint_rounded),
      label: 'Attendance',
      color: AppColors.primary,
      path: '/attendance',
    ),
    _EmployeeModule(
      icon: Icon(Icons.history_rounded),
      label: 'History',
      color: AppColors.secondary,
      path: '/attendance/history',
    ),
    _EmployeeModule(
      icon: Icon(Icons.how_to_reg_rounded),
      label: 'Admission',
      color: AppColors.info,
      path: '/admissions',
    ),
    _EmployeeModule(
      icon: Icon(Icons.event_note_rounded),
      label: 'Leave',
      color: AppColors.accent,
      path: '/leaves',
    ),
    _EmployeeModule(
      icon: Icon(Icons.flag_rounded),
      label: 'My Targets',
      color: AppColors.info,
      path: '/admissions/targets',
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.screenPadding,
        AppSpacing.sm,
        AppSpacing.screenPadding,
        AppSpacing.bottomNavHeight + AppSpacing.xl,
      ),
      children: [
        PgWelcomeCard(
          name: name,
          dateLabel: DateFormat('EEEE, d MMM yyyy').format(DateTime.now()),
          photoUrl: photoUrl,
          role: role,
          padding: const EdgeInsets.all(AppSpacing.md),
          avatarRadius: 26,
        ),
        const SizedBox(height: AppSpacing.md),
        _AdmissionTargetPerformanceCard(
          preset: preset,
          summary: summary ?? AdmissionTargetSummary.empty,
          loading: loading,
          error: error,
          onPreset: onPreset,
          onRetry: onRetry,
        ),
        const SizedBox(height: AppSpacing.md),
        Text(
          'Modules',
          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
              ),
        ),
        const SizedBox(height: AppSpacing.sm),
        LayoutBuilder(
          builder: (context, constraints) {
            const gap = 10.0;
            final width = (constraints.maxWidth - gap) / 2;
            return Wrap(
              spacing: gap,
              runSpacing: gap,
              children: [
                for (final module in _modules)
                  SizedBox(
                    width: width,
                    child: _EmployeeModuleCard(
                      module: module,
                      onTap: () => onOpen(module.path),
                    ),
                  ),
              ],
            );
          },
        ),
      ],
    );
  }
}

class _AdmissionTargetPerformanceCard extends StatelessWidget {
  const _AdmissionTargetPerformanceCard({
    required this.preset,
    required this.summary,
    required this.loading,
    required this.onPreset,
    required this.onRetry,
    this.error,
  });

  final String preset;
  final AdmissionTargetSummary summary;
  final bool loading;
  final Object? error;
  final ValueChanged<String> onPreset;
  final VoidCallback onRetry;

  static const _filters = <(String, String)>[
    ('This Week', 'this_week'),
    ('Last Week', 'last_week'),
    ('This Month', 'this_month'),
    ('Last Month', 'last_month'),
  ];

  @override
  Widget build(BuildContext context) {
    return PgCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Admission Target Performance',
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: AppSpacing.sm),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              for (final filter in _filters)
                _PeriodChip(
                  label: filter.$1,
                  selected: preset == filter.$2,
                  onTap: () => onPreset(filter.$2),
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          if (loading)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 20),
              child: Center(
                child: SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(strokeWidth: 2.4),
                ),
              ),
            )
          else if (error != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'Unable to load target performance.',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ),
                  TextButton(onPressed: onRetry, child: const Text('Retry')),
                ],
              ),
            )
          else ...[
            Row(
              children: [
                _MetricStat(label: 'Current Target', value: '${summary.target}'),
                _MetricStat(label: 'Achieved', value: '${summary.achieved}'),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            Row(
              children: [
                _MetricStat(label: 'Remaining', value: '${summary.remaining}'),
                _MetricStat(
                  label: 'Achievement %',
                  value: _percentLabel(summary.percentage, summary.target),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.md),
            PgProgressBar(
              label: 'Progress',
              percentage: summary.target > 0 ? summary.percentage : null,
              currentLabel: '${summary.achieved} achieved',
              targetLabel: '${summary.target}',
            ),
          ],
        ],
      ),
    );
  }

  static String _percentLabel(double percentage, int target) {
    if (target <= 0) return 'N/A';
    if (percentage == percentage.roundToDouble()) {
      return '${percentage.toInt()}%';
    }
    return '${percentage.toStringAsFixed(1)}%';
  }
}

class _PeriodChip extends StatelessWidget {
  const _PeriodChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected
          ? AppColors.primary
          : AppColors.primary.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          child: Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: selected ? Colors.white : AppColors.primary,
                  fontWeight: FontWeight.w700,
                ),
          ),
        ),
      ),
    );
  }
}

class _MetricStat extends StatelessWidget {
  const _MetricStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        children: [
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              value,
              maxLines: 1,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                  ),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  fontSize: 10,
                  height: 1.15,
                ),
          ),
        ],
      ),
    );
  }
}

class _EmployeeModuleCard extends StatelessWidget {
  const _EmployeeModuleCard({required this.module, required this.onTap});

  final _EmployeeModule module;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return PgCard(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
      onTap: onTap,
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: module.color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: IconTheme(
              data: IconThemeData(color: module.color, size: 18),
              child: Center(child: module.icon),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              module.label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmployeeModule {
  const _EmployeeModule({
    required this.icon,
    required this.label,
    required this.color,
    required this.path,
  });

  final Widget icon;
  final String label;
  final Color color;
  final String path;
}
