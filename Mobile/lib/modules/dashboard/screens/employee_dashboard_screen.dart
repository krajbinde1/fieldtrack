import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
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
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.screenPadding,
        AppSpacing.sm,
        AppSpacing.screenPadding,
        AppSpacing.bottomNavHeight + AppSpacing.md,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          PgWelcomeCard(
            name: name,
            dateLabel: DateFormat('EEEE, d MMM yyyy').format(DateTime.now()),
            photoUrl: photoUrl,
            role: role,
            padding: const EdgeInsets.all(AppSpacing.md),
            avatarRadius: 26,
          ),
          const SizedBox(height: AppSpacing.sm),
          _AdmissionTargetPerformanceStrip(
            preset: preset,
            summary: summary ?? AdmissionTargetSummary.empty,
            loading: loading,
            error: error,
            onPreset: onPreset,
            onRetry: onRetry,
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            'Modules',
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: AppSpacing.sm),
          Expanded(
            child: _EmployeeModuleGrid(
              modules: _modules,
              onOpen: onOpen,
            ),
          ),
        ],
      ),
    );
  }
}

class _AdmissionTargetPerformanceStrip extends StatelessWidget {
  const _AdmissionTargetPerformanceStrip({
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

  static String labelFor(String preset) {
    for (final filter in _filters) {
      if (filter.$2 == preset) return filter.$1;
    }
    return 'This Week';
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 8, 6, 10),
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
        border: Border.all(color: AppColors.border.withValues(alpha: 0.7)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Admission Target Performance',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
              ),
              _PeriodFilterButton(
                preset: preset,
                filters: _filters,
                onPreset: onPreset,
              ),
            ],
          ),
          const SizedBox(height: 6),
          if (loading)
            const SizedBox(
              height: 36,
              child: Center(
                child: SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              ),
            )
          else if (error != null)
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Unable to load target performance.',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
                TextButton(
                  onPressed: onRetry,
                  child: const Text('Retry'),
                ),
              ],
            )
          else ...[
            Row(
              children: [
                _MetricStat(label: 'Target', value: '${summary.target}'),
                _MetricStat(label: 'Achieved', value: '${summary.achieved}'),
                _MetricStat(label: 'Remaining', value: '${summary.remaining}'),
                _MetricStat(
                  label: '%',
                  value: _percentLabel(summary.percentage, summary.target),
                ),
              ],
            ),
            const SizedBox(height: 8),
            _SlimProgressBar(
              percentage: summary.target > 0 ? summary.percentage : null,
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

class _PeriodFilterButton extends StatelessWidget {
  const _PeriodFilterButton({
    required this.preset,
    required this.filters,
    required this.onPreset,
  });

  final String preset;
  final List<(String, String)> filters;
  final ValueChanged<String> onPreset;

  @override
  Widget build(BuildContext context) {
    return PopupMenuButton<String>(
      tooltip: 'Period',
      padding: EdgeInsets.zero,
      initialValue: preset,
      onSelected: onPreset,
      itemBuilder: (context) => [
        for (final filter in filters)
          PopupMenuItem<String>(
            value: filter.$2,
            child: Text(filter.$1),
          ),
      ],
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              _AdmissionTargetPerformanceStrip.labelFor(preset),
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: AppColors.primary,
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(width: 2),
            IconTheme(
              data: const IconThemeData(color: AppColors.primary, size: 18),
              child: const Icon(Icons.filter_alt),
            ),
          ],
        ),
      ),
    );
  }
}

class _SlimProgressBar extends StatelessWidget {
  const _SlimProgressBar({required this.percentage});

  final double? percentage;

  @override
  Widget build(BuildContext context) {
    final value = percentage == null ? 0.0 : (percentage! / 100).clamp(0.0, 1.0);
    return ClipRRect(
      borderRadius: BorderRadius.circular(999),
      child: SizedBox(
        height: 5,
        child: Stack(
          children: [
            Container(color: const Color(0xFFE2E8F0)),
            FractionallySizedBox(
              widthFactor: value,
              child: Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(colors: AppColors.tealGradient),
                ),
              ),
            ),
          ],
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
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                    height: 1.1,
                  ),
            ),
          ),
          Text(
            label,
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  fontSize: 10,
                  height: 1.1,
                ),
          ),
        ],
      ),
    );
  }
}

class _EmployeeModuleGrid extends StatelessWidget {
  const _EmployeeModuleGrid({
    required this.modules,
    required this.onOpen,
  });

  final List<_EmployeeModule> modules;
  final ValueChanged<String> onOpen;

  static const _gap = 12.0;

  @override
  Widget build(BuildContext context) {
    final rows = <List<_EmployeeModule>>[];
    for (var i = 0; i < modules.length; i += 2) {
      rows.add(
        modules.sublist(i, i + 2 > modules.length ? modules.length : i + 2),
      );
    }

    return LayoutBuilder(
      builder: (context, constraints) {
        final cardWidth = (constraints.maxWidth - _gap) / 2;
        return Column(
          children: [
            for (var i = 0; i < rows.length; i++) ...[
              if (i > 0) const SizedBox(height: _gap),
              Expanded(
                child: rows[i].length == 2
                    ? Row(
                        children: [
                          Expanded(
                            child: _EmployeeModuleCard(
                              module: rows[i][0],
                              onTap: () => onOpen(rows[i][0].path),
                            ),
                          ),
                          const SizedBox(width: _gap),
                          Expanded(
                            child: _EmployeeModuleCard(
                              module: rows[i][1],
                              onTap: () => onOpen(rows[i][1].path),
                            ),
                          ),
                        ],
                      )
                    : Align(
                        child: SizedBox(
                          width: cardWidth,
                          child: _EmployeeModuleCard(
                            module: rows[i][0],
                            onTap: () => onOpen(rows[i][0].path),
                          ),
                        ),
                      ),
              ),
            ],
          ],
        );
      },
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
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      onTap: onTap,
      child: Center(
        child: FittedBox(
          fit: BoxFit.scaleDown,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: module.color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: IconTheme(
                  data: IconThemeData(color: module.color, size: 30),
                  child: Center(child: module.icon),
                ),
              ),
              const SizedBox(height: 10),
              Text(
                module.label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
              ),
            ],
          ),
        ),
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
