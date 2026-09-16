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
import '../../attendance/models/attendance.dart';
import '../../attendance/models/attendance_format.dart';
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
    await context.push(path);
    if (!mounted) return;
    if (path.startsWith('/attendance')) {
      await ref.read(todayAttendanceProvider.notifier).refresh();
    }
    if (path.startsWith('/admissions')) {
      await _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final today = ref.watch(todayAttendanceProvider);
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
          locationName: widget.auth.session?.employee.baseLocation,
          attendance: today.value,
          attendanceLoading: today.isLoading && !today.hasValue,
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
    this.locationName,
    this.attendance,
    this.attendanceLoading = false,
    this.summary,
    this.error,
  });

  final String name;
  final String role;
  final String? photoUrl;
  final String? locationName;
  final Attendance? attendance;
  final bool attendanceLoading;
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
      subtitle: 'Mark your attendance',
      color: AppColors.primary,
      path: '/attendance',
    ),
    _EmployeeModule(
      icon: Icon(Icons.how_to_reg_rounded),
      label: 'Admission',
      subtitle: 'Add new admission',
      color: AppColors.info,
      path: '/admissions',
    ),
    _EmployeeModule(
      icon: Icon(Icons.event_note_rounded),
      label: 'Leave',
      subtitle: 'Apply and track leave',
      color: AppColors.accent,
      path: '/leaves',
    ),
    _EmployeeModule(
      icon: Icon(Icons.flag_rounded),
      label: 'My Targets',
      subtitle: 'View targets & performance',
      color: Color(0xFF7C3AED),
      path: '/admissions/targets',
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        return SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.screenPadding,
            AppSpacing.sm,
            AppSpacing.screenPadding,
            AppSpacing.bottomNavHeight + AppSpacing.md,
          ),
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: constraints.maxHeight),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                PgWelcomeCard(
                  name: name,
                  dateLabel:
                      DateFormat('EEEE, d MMM yyyy').format(DateTime.now()),
                  photoUrl: photoUrl,
                  role: role,
                  prominent: true,
                  padding: const EdgeInsets.fromLTRB(20, 22, 20, 22),
                  avatarRadius: 36,
                ),
                const SizedBox(height: 12),
                _PunchStatusCard(
                  attendance: attendance,
                  loading: attendanceLoading,
                  locationName: locationName,
                  onPunchIn: () => onOpen('/attendance/punch-in'),
                  onPunchOut: () => onOpen('/attendance/punch-out'),
                ),
                const SizedBox(height: 12),
                _AdmissionTargetCard(
                  preset: preset,
                  summary: summary ?? AdmissionTargetSummary.empty,
                  loading: loading,
                  error: error,
                  onPreset: onPreset,
                  onRetry: onRetry,
                ),
                const SizedBox(height: 12),
                _EmployeeModuleGrid(
                  modules: _modules,
                  onOpen: onOpen,
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _PunchStatusCard extends StatelessWidget {
  const _PunchStatusCard({
    required this.attendance,
    required this.loading,
    required this.onPunchIn,
    required this.onPunchOut,
    this.locationName,
  });

  final Attendance? attendance;
  final bool loading;
  final String? locationName;
  final VoidCallback onPunchIn;
  final VoidCallback onPunchOut;

  @override
  Widget build(BuildContext context) {
    final record = attendance;
    final punchedOut = record != null && record.punchOut != null;
    final punchedIn = record != null && record.canPunchOut;
    final showLoadingAction = loading && record == null;

    final Color iconColor;
    final Color iconBg;
    final Widget statusIcon;
    final Widget copy;
    if (punchedOut) {
      iconColor = AppColors.approvedFg;
      iconBg = AppColors.approvedBg;
      statusIcon = const Icon(Icons.verified_rounded);
      copy = _PunchedOutCopy(attendance: record);
    } else if (punchedIn) {
      iconColor = AppColors.success;
      iconBg = AppColors.success.withValues(alpha: 0.12);
      statusIcon = const Icon(Icons.check_circle_rounded);
      copy = _PunchedInCopy(
        attendance: record,
        locationName: locationName,
      );
    } else {
      iconColor = AppColors.primary;
      iconBg = AppColors.primary.withValues(alpha: 0.10);
      statusIcon = const Icon(Icons.fingerprint_rounded);
      copy = Text(
        'Not Punched In',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: Theme.of(context).textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w800,
              height: 1.15,
            ),
      );
    }

    return PgCard(
      padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: iconBg,
              shape: BoxShape.circle,
            ),
            child: IconTheme(
              data: IconThemeData(color: iconColor, size: 22),
              child: statusIcon,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(child: copy),
          if (showLoadingAction) ...[
            const SizedBox(width: 8),
            const SizedBox(
              width: 36,
              height: 36,
              child: Center(
                child: SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2.2),
                ),
              ),
            ),
          ] else if (!punchedOut) ...[
            const SizedBox(width: 8),
            punchedIn
                ? _PunchActionButton(
                    label: 'Punch Out',
                    tonal: true,
                    onPressed: onPunchOut,
                  )
                : _PunchActionButton(
                    label: 'Punch In',
                    onPressed: onPunchIn,
                  ),
          ],
        ],
      ),
    );
  }
}

class _PunchedInCopy extends StatelessWidget {
  const _PunchedInCopy({
    required this.attendance,
    this.locationName,
  });

  final Attendance attendance;
  final String? locationName;

  @override
  Widget build(BuildContext context) {
    final address = attendance.inAddress?.trim();
    final location = (address != null && address.isNotEmpty)
        ? address
        : locationName?.trim();
    final timeLabel = AttendanceFormat.time(attendance.punchIn);
    final metaStyle = Theme.of(context).textTheme.labelSmall?.copyWith(
          fontSize: 11,
          height: 1.2,
          fontWeight: FontWeight.w600,
          color: AppColors.textSecondary,
        );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'You are',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                fontSize: 11,
                height: 1.1,
                fontWeight: FontWeight.w600,
                color: AppColors.textSecondary,
              ),
        ),
        Text(
          'Punched In',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
                height: 1.15,
              ),
        ),
        const SizedBox(height: 2),
        Row(
          children: [
            IconTheme(
              data: const IconThemeData(
                color: AppColors.textMuted,
                size: 13,
              ),
              child: const Icon(Icons.schedule_rounded),
            ),
            const SizedBox(width: 4),
            Flexible(
              child: Text(
                timeLabel,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: metaStyle,
              ),
            ),
          ],
        ),
        if (location != null && location.isNotEmpty) ...[
          const SizedBox(height: 2),
          Row(
            children: [
              IconTheme(
                data: const IconThemeData(
                  color: AppColors.textMuted,
                  size: 13,
                ),
                child: const Icon(Icons.location_on_outlined),
              ),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  location,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: metaStyle,
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }
}

class _PunchedOutCopy extends StatelessWidget {
  const _PunchedOutCopy({required this.attendance});

  final Attendance attendance;

  @override
  Widget build(BuildContext context) {
    final duration = _workingDuration(attendance);
    final metaStyle = Theme.of(context).textTheme.labelSmall?.copyWith(
          fontSize: 11,
          height: 1.2,
          fontWeight: FontWeight.w600,
          color: AppColors.textSecondary,
        );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Punched Out',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
                height: 1.15,
              ),
        ),
        const SizedBox(height: 2),
        Text(
          'In ${AttendanceFormat.time(attendance.punchIn)}  ·  Out ${AttendanceFormat.time(attendance.punchOut)}',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: metaStyle,
        ),
        if (duration != null) ...[
          const SizedBox(height: 2),
          Row(
            children: [
              IconTheme(
                data: const IconThemeData(
                  color: AppColors.textMuted,
                  size: 13,
                ),
                child: const Icon(Icons.schedule_rounded),
              ),
              const SizedBox(width: 4),
              Flexible(
                child: Text(
                  duration,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: metaStyle,
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }

  static String? _workingDuration(Attendance record) {
    final hours = record.workingHours?.trim();
    if (hours != null && hours.isNotEmpty) return hours;
    final punchIn = record.punchIn;
    final punchOut = record.punchOut;
    if (punchIn == null || punchOut == null) return null;
    final minutes = punchOut.difference(punchIn).inMinutes;
    final safe = minutes < 0 ? 0 : minutes;
    return '${safe ~/ 60}h ${(safe % 60).toString().padLeft(2, '0')}m';
  }
}

class _PunchActionButton extends StatelessWidget {
  const _PunchActionButton({
    required this.label,
    required this.onPressed,
    this.tonal = false,
  });

  final String label;
  final VoidCallback onPressed;
  final bool tonal;

  static final ButtonStyle _style = FilledButton.styleFrom(
    visualDensity: VisualDensity.compact,
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
    minimumSize: const Size(0, 36),
    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(10),
    ),
    textStyle: const TextStyle(
      fontSize: 12,
      fontWeight: FontWeight.w700,
    ),
  );

  @override
  Widget build(BuildContext context) {
    if (tonal) {
      return FilledButton.tonal(
        onPressed: onPressed,
        style: _style,
        child: Text(label),
      );
    }
    return FilledButton(
      onPressed: onPressed,
      style: _style,
      child: Text(label),
    );
  }
}

class _AdmissionTargetCard extends StatelessWidget {
  const _AdmissionTargetCard({
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
    final period = labelFor(preset);
    return PgCard(
      padding: const EdgeInsets.fromLTRB(14, 12, 10, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Admission Target ($period)',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
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
          const SizedBox(height: 10),
          if (loading)
            const SizedBox(
              height: 52,
              child: Center(
                child: SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2.2),
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
                TextButton(onPressed: onRetry, child: const Text('Retry')),
              ],
            )
          else ...[
            IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _MetricStat(label: 'Target', value: '${summary.target}'),
                  const _MetricDivider(),
                  _MetricStat(label: 'Achieved', value: '${summary.achieved}'),
                  const _MetricDivider(),
                  _MetricStat(label: 'Remaining', value: '${summary.remaining}'),
                  const _MetricDivider(),
                  _MetricStat(
                    label: 'Achievement %',
                    value: _percentLabel(summary.percentage, summary.target),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            _ThickProgressBar(
              percentage: summary.target > 0 ? summary.percentage : null,
            ),
            const SizedBox(height: 6),
            Text(
              'Achieved ${summary.achieved} of ${summary.target}',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: AppColors.textSecondary,
                  ),
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

class _MetricDivider extends StatelessWidget {
  const _MetricDivider();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 1,
      margin: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
      color: AppColors.border.withValues(alpha: 0.9),
    );
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
      child: Container(
        padding: const EdgeInsets.fromLTRB(8, 4, 6, 4),
        decoration: BoxDecoration(
          color: AppColors.primary.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              _AdmissionTargetCard.labelFor(preset),
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: AppColors.primary,
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(width: 2),
            IconTheme(
              data: const IconThemeData(color: AppColors.primary, size: 16),
              child: const Icon(Icons.expand_more_rounded),
            ),
          ],
        ),
      ),
    );
  }
}

class _ThickProgressBar extends StatelessWidget {
  const _ThickProgressBar({required this.percentage});

  final double? percentage;

  @override
  Widget build(BuildContext context) {
    final value = percentage == null ? 0.0 : (percentage! / 100).clamp(0.0, 1.0);
    return ClipRRect(
      borderRadius: BorderRadius.circular(999),
      child: SizedBox(
        height: 8,
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
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              value,
              maxLines: 1,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                    height: 1.05,
                  ),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  fontSize: 10,
                  height: 1.15,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textSecondary,
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
        final scale = (constraints.maxWidth / 360).clamp(0.86, 1.08);
        final cardHeight = (100.0 * scale).clamp(90.0, 112.0);
        final iconSize = (46.0 * scale).clamp(40.0, 52.0);
        final cardWidth = (constraints.maxWidth - _gap) / 2;

        return Column(
          children: [
            for (var i = 0; i < rows.length; i++) ...[
              if (i > 0) const SizedBox(height: _gap),
              SizedBox(
                height: cardHeight,
                child: rows[i].length == 2
                    ? Row(
                        children: [
                          Expanded(
                            child: _EmployeeModuleCard(
                              module: rows[i][0],
                              iconSize: iconSize,
                              onTap: () => onOpen(rows[i][0].path),
                            ),
                          ),
                          const SizedBox(width: _gap),
                          Expanded(
                            child: _EmployeeModuleCard(
                              module: rows[i][1],
                              iconSize: iconSize,
                              onTap: () => onOpen(rows[i][1].path),
                            ),
                          ),
                        ],
                      )
                    : Align(
                        alignment: Alignment.centerLeft,
                        child: SizedBox(
                          width: cardWidth,
                          child: _EmployeeModuleCard(
                            module: rows[i][0],
                            iconSize: iconSize,
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
  const _EmployeeModuleCard({
    required this.module,
    required this.iconSize,
    required this.onTap,
  });

  final _EmployeeModule module;
  final double iconSize;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final tint = Color.alphaBlend(
      module.color.withValues(alpha: 0.12),
      Colors.white,
    );
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Ink(
          decoration: BoxDecoration(
            color: tint,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: module.color.withValues(alpha: 0.08)),
            boxShadow: [
              BoxShadow(
                color: module.color.withValues(alpha: 0.10),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(10, 10, 6, 10),
            child: Row(
              children: [
                Container(
                  width: iconSize,
                  height: iconSize,
                  decoration: BoxDecoration(
                    color: module.color,
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: module.color.withValues(alpha: 0.28),
                        blurRadius: 8,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  child: IconTheme(
                    data: IconThemeData(
                      color: Colors.white,
                      size: iconSize * 0.5,
                    ),
                    child: Center(child: module.icon),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        module.label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                              height: 1.1,
                            ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        module.subtitle,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              fontSize: 11,
                              height: 1.2,
                              color: AppColors.textSecondary,
                            ),
                      ),
                    ],
                  ),
                ),
                IconTheme(
                  data: const IconThemeData(
                    color: AppColors.textMuted,
                    size: 18,
                  ),
                  child: const Icon(Icons.chevron_right_rounded),
                ),
              ],
            ),
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
    required this.subtitle,
    required this.color,
    required this.path,
  });

  final Widget icon;
  final String label;
  final String subtitle;
  final Color color;
  final String path;
}

