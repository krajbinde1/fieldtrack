import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/api/api_client.dart';
import '../../../core/auth/user_role.dart';
import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_welcome_card.dart';
import '../../../core/routing/center_scope.dart';
import '../../attendance/models/attendance.dart';
import '../../attendance/models/attendance_format.dart';
import '../../attendance/providers/attendance_provider.dart';
import '../../auth/providers/auth_controller.dart';
import '../../manager/api/manager_api.dart';

class SupervisorDashboardScreen extends ConsumerStatefulWidget {
  const SupervisorDashboardScreen({super.key, required this.auth});
  final AuthController auth;

  @override
  ConsumerState<SupervisorDashboardScreen> createState() =>
      _SupervisorDashboardScreenState();
}

class _SupervisorDashboardScreenState
    extends ConsumerState<SupervisorDashboardScreen> {
  late Future<_DashboardSnapshot> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_DashboardSnapshot> _load() async {
    final dio = ApiClient(
      SessionStore(),
      onUnauthorized: widget.auth.sessionExpired,
    ).dio;
    final api = ManagerApi(dio);
    Map<String, dynamic> data = const {};
    try {
      final response = await dio.get('/dashboard');
      final body = response.data;
      if (body is Map && body['data'] is Map) {
        data = Map<String, dynamic>.from(body['data'] as Map);
      }
    } catch (_) {}

    TeamAttendancePulse? pulse;
    if (widget.auth.userRole.isProjectHead) {
      try {
        final result = await api.listTeamAttendance();
        pulse = TeamAttendancePulse.fromRows(result.rows, result.meta);
      } catch (_) {}
    }

    return _DashboardSnapshot(data: data, pulse: pulse);
  }

  Future<void> _refresh() async {
    if (widget.auth.userRole.isCenterManager) {
      ref.invalidate(todayAttendanceProvider);
    }
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  Future<void> _open(String path) async {
    await context.push(path);
    if (!mounted) return;
    if (widget.auth.userRole.isCenterManager &&
        path.startsWith('/attendance')) {
      await ref.read(todayAttendanceProvider.notifier).refresh();
    }
    await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    final role = widget.auth.userRole;
    final session = widget.auth.session;
    final displayName = session?.displayName.trim() ?? '';
    final name = displayName.isNotEmpty ? displayName : role.label;
    final ownAttendance = role.isCenterManager
        ? ref.watch(todayAttendanceProvider).maybeWhen(
              data: (value) => value,
              orElse: () => null,
            )
        : null;

    return PgPageScaffold(
      auth: widget.auth,
      body: FutureBuilder<_DashboardSnapshot>(
        future: _future,
        builder: (context, snapshot) {
          return RefreshIndicator(
            onRefresh: _refresh,
            child: SupervisorDashboardView(
              name: name,
              role: role,
              data: snapshot.data?.data ?? const {},
              pulse: snapshot.data?.pulse,
              ownAttendance: ownAttendance,
              onOpen: _open,
            ),
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
    this.pulse,
    this.ownAttendance,
    this.centerId,
    this.centerName,
    this.centerManagerName,
    this.schemeName,
    this.directorCenterView = false,
  });

  final String name;
  final UserRole role;
  final Map<String, dynamic> data;
  final TeamAttendancePulse? pulse;
  final Attendance? ownAttendance;
  final int? centerId;
  final String? centerName;
  final String? centerManagerName;
  final String? schemeName;
  final bool directorCenterView;
  final ValueChanged<String> onOpen;

  String get _prefix =>
      role.isAdmin || role.isDirector ? '/director' : '/manager';

  bool get _centerScopedDirector => directorCenterView && centerId != null;

  String _path(String path) => withCenterId(path, centerId);

  int _count(String key) {
    final value = data[key];
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse('$value') ?? 0;
  }

  List<_DashboardTile> get _summary {
    return [
      if ((role.isDirector || role.isAdmin) && !_centerScopedDirector)
        _DashboardTile(
          icon: const Icon(Icons.apartment_rounded),
          label: 'Total Centers',
          value: '${_count('active_centers') == 0 && _count('centers') > 0 ? _count('centers') : _count('active_centers')}',
          path: '/director/centers',
          color: const Color(0xFF0369A1),
          background: const Color(0xFFE0F2FE),
        ),
      _DashboardTile(
        icon: const Icon(Icons.groups_rounded),
        label: 'Employees',
        value: '${_count('employees')}',
        path: _centerScopedDirector
            ? _path('/director/employees')
            : (role.isCenterManager || role.isProjectHead
                ? '/manager/employees'
                : '$_prefix/team-attendance'),
        color: const Color(0xFF2563EB),
        background: const Color(0xFFE8F1FF),
      ),
      _DashboardTile(
        icon: const Icon(Icons.fingerprint_rounded),
        label: 'Punched In',
        value: '${_count('punched_in_today')}',
        path: _path('$_prefix/team-attendance?status=punched_in'),
        color: const Color(0xFF0F766E),
        background: const Color(0xFFE6F7F1),
      ),
      _DashboardTile(
        icon: const Icon(Icons.route_rounded),
        label: 'Active Routes',
        value: '${_count('active_routes')}',
        path: _path('$_prefix/route-tracking'),
        color: const Color(0xFF7C3AED),
        background: const Color(0xFFF0E9FF),
      ),
      _DashboardTile(
        icon: const Icon(Icons.event_note_rounded),
        label: 'Pending Leave',
        value: '${_count('pending_leaves')}',
        path: _path('$_prefix/leaves?status=pending'),
        color: const Color(0xFFEA580C),
        background: const Color(0xFFFFF6E5),
      ),
      _DashboardTile(
        icon: const Icon(Icons.flag_rounded),
        label: 'Targets',
        value: '${_count('admission_targets')}',
        path: _centerScopedDirector
            ? _path('/director/admission-targets')
            : (role.isCenterManager || role.isProjectHead
                ? '/manager/admission-targets'
                : '$_prefix/admissions'),
        color: const Color(0xFFDB2777),
        background: const Color(0xFFFDE8F0),
      ),
      if (role.isCenterManager)
        _DashboardTile(
          icon: const Icon(Icons.access_time_filled_rounded),
          label: 'My Attendance',
          value: _ownAttendanceValue,
          path: '/attendance',
          color: const Color(0xFF0D9488),
          background: const Color(0xFFD1FAE5),
        ),
      if (_centerScopedDirector)
        _DashboardTile(
          icon: const Icon(Icons.how_to_reg_rounded),
          label: 'Admissions',
          value: '${_count('admissions')}',
          path: _path('/director/admissions'),
          color: const Color(0xFF0EA5E9),
          background: const Color(0xFFE0F4FF),
        ),
    ];
  }

  String get _ownAttendanceValue {
    final record = ownAttendance;
    if (record == null || record.punchIn == null) return '—';
    if (record.punchOut != null) return 'Out';
    return 'In';
  }

  List<_DashboardTile> get _modules {
    return [
      if (role.isCenterManager || _centerScopedDirector)
        _DashboardTile(
          icon: const Icon(Icons.groups_rounded),
          label: 'Users / Employees',
          subtitle: _centerScopedDirector
              ? 'Staff in this center'
              : 'Staff in assigned center(s)',
          path: _centerScopedDirector
              ? _path('/director/employees')
              : '/manager/employees',
          color: const Color(0xFF2563EB),
          background: const Color(0xFFE8F1FF),
        ),
      _DashboardTile(
        icon: const Icon(Icons.how_to_reg_rounded),
        label: 'Admissions',
        subtitle: 'Review field admissions',
        path: _path('$_prefix/admissions'),
        color: const Color(0xFF0EA5E9),
        background: const Color(0xFFE0F4FF),
      ),
      if (role.isCenterManager || _centerScopedDirector)
        _DashboardTile(
          icon: const Icon(Icons.flag_rounded),
          label: 'Admission Targets',
          subtitle: _centerScopedDirector
              ? 'Targets for this center'
              : 'Set employee/Mobilizer targets',
          path: _centerScopedDirector
              ? _path('/director/admission-targets')
              : '/manager/admission-targets',
          color: const Color(0xFFDB2777),
          background: const Color(0xFFFDE8F0),
        ),
      if (!role.isCenterManager)
        _DashboardTile(
          icon: const Icon(Icons.event_available_rounded),
          label: 'Attendance',
          subtitle: 'Daily team attendance',
          path: _path('$_prefix/team-attendance'),
          color: const Color(0xFF0F766E),
          background: const Color(0xFFE6F7F1),
        ),
      _DashboardTile(
        icon: const Icon(Icons.route_rounded),
        label: 'Employee Routes',
        subtitle: 'Live field routes',
        path: _path('$_prefix/route-tracking'),
        color: const Color(0xFF7C3AED),
        background: const Color(0xFFF0E9FF),
      ),
      _DashboardTile(
        icon: const Icon(Icons.event_note_rounded),
        label: 'Leave Requests',
        subtitle: 'Approve team leave',
        path: _path('$_prefix/leaves'),
        color: const Color(0xFFEA580C),
        background: const Color(0xFFFFF6E5),
      ),
      if (role.isCenterManager || role.isProjectHead || _centerScopedDirector)
        _DashboardTile(
          icon: const Icon(Icons.analytics_rounded),
          label: 'Reports',
          subtitle: _centerScopedDirector
              ? 'Reports for this center'
              : 'Assigned-center reports',
          path: _centerScopedDirector
              ? _path('/director/reports')
              : '/manager/reports',
          color: const Color(0xFF4F46E5),
          background: const Color(0xFFEEF2FF),
        ),
    ];
  }

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: const Color(0xFFFAFBFC),
      child: ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.fromLTRB(
        AppSpacing.screenPadding,
        AppSpacing.sm,
        AppSpacing.screenPadding,
        _centerScopedDirector
            ? AppSpacing.xl
            : AppSpacing.bottomNavHeight + AppSpacing.md,
      ),
      children: [
        if (_centerScopedDirector)
          _SelectedCenterHeader(
            centerName: centerName ?? 'Center',
            schemeName: schemeName,
            managerName: centerManagerName,
          )
        else
          PgWelcomeCard(
            name: name,
            dateLabel: DateFormat('EEEE, d MMM yyyy').format(DateTime.now()),
            role: role.label,
            prominent: true,
            padding: const EdgeInsets.fromLTRB(20, 22, 20, 22),
            avatarRadius: 36,
          ),
        const SizedBox(height: 12),
        _AttendanceStatusCard(
          pulse: role.isCenterManager
              ? TeamAttendancePulse.fromOwn(ownAttendance)
              : pulse,
          ownAttendance: role.isCenterManager ? ownAttendance : null,
          punchedIn: _count('punched_in_today'),
          punchedOut: _count('punched_out_today'),
          onDetails: () => onOpen(
            role.isCenterManager
                ? '/attendance'
                : _path('$_prefix/team-attendance'),
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        _SummaryGrid(tiles: _summary, onOpen: onOpen),
        const SizedBox(height: AppSpacing.lg),
        Text(
          'Modules',
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
              ),
        ),
        const SizedBox(height: AppSpacing.sm),
        _ModuleGrid(tiles: _modules, onOpen: onOpen),
      ],
    ),
    );
  }
}

class _DashboardSnapshot {
  const _DashboardSnapshot({required this.data, this.pulse});

  final Map<String, dynamic> data;
  final TeamAttendancePulse? pulse;
}

class _SelectedCenterHeader extends StatelessWidget {
  const _SelectedCenterHeader({
    required this.centerName,
    this.schemeName,
    this.managerName,
  });

  final String centerName;
  final String? schemeName;
  final String? managerName;

  @override
  Widget build(BuildContext context) {
    final scheme = schemeName?.trim() ?? '';
    final manager = managerName?.trim() ?? '';
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF0F766E), Color(0xFF14B8A6)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: const [
          BoxShadow(
            color: Color(0x140F766E),
            blurRadius: 18,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Selected Center',
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: Colors.white.withValues(alpha: 0.8),
                  fontWeight: FontWeight.w600,
                ),
          ),
          const SizedBox(height: 4),
          Text(
            centerName,
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  height: 1.15,
                ),
          ),
          if (scheme.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              scheme,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Colors.white.withValues(alpha: 0.9),
                    fontWeight: FontWeight.w600,
                  ),
            ),
          ],
          const SizedBox(height: 12),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.16),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Row(
              children: [
                const Icon(Icons.badge_rounded, color: Colors.white, size: 20),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Center Manager',
                        style: Theme.of(context).textTheme.labelSmall?.copyWith(
                              color: Colors.white.withValues(alpha: 0.75),
                              fontWeight: FontWeight.w600,
                            ),
                      ),
                      Text(
                        manager.isEmpty ? 'Not assigned' : manager,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class TeamAttendancePulse {
  const TeamAttendancePulse({
    required this.status,
    required this.punchInTime,
    required this.punchOutTime,
    required this.workingDuration,
    this.livePunchIn,
  });

  final String status;
  final String punchInTime;
  final String punchOutTime;
  final String workingDuration;
  final DateTime? livePunchIn;

  static TeamAttendancePulse fromOwn(Attendance? attendance) {
    if (attendance == null || attendance.punchIn == null) {
      return const TeamAttendancePulse(
        status: 'Not Punched In',
        punchInTime: '—',
        punchOutTime: '—',
        workingDuration: '—',
      );
    }
    if (attendance.punchOut != null) {
      return TeamAttendancePulse(
        status: 'Punched Out',
        punchInTime: AttendanceFormat.time(attendance.punchIn),
        punchOutTime: AttendanceFormat.time(attendance.punchOut),
        workingDuration: attendance.workingHours ?? '—',
      );
    }
    return TeamAttendancePulse(
      status: 'Punched In',
      punchInTime: AttendanceFormat.time(attendance.punchIn),
      punchOutTime: '—',
      workingDuration: attendance.workingHours ?? '—',
      livePunchIn: attendance.punchIn,
    );
  }

  static TeamAttendancePulse fromRows(
    List<Map<String, dynamic>> rows, [
    Map<String, dynamic> meta = const {},
  ]) {
    final metaPunchedIn = int.tryParse('${meta['punched_in'] ?? 0}') ?? 0;
    final metaPunchedOut = int.tryParse('${meta['punched_out'] ?? 0}') ?? 0;
    Map<String, dynamic>? working;
    Map<String, dynamic>? completed;
    DateTime? earliestIn;
    DateTime? latestOut;

    for (final row in rows) {
      final punchIn = DateTime.tryParse('${row['punch_in_time'] ?? ''}');
      final punchOut = DateTime.tryParse('${row['punch_out_time'] ?? ''}');
      if (punchIn != null && (earliestIn == null || punchIn.isBefore(earliestIn))) {
        earliestIn = punchIn;
      }
      if (punchOut != null && (latestOut == null || punchOut.isAfter(latestOut))) {
        latestOut = punchOut;
      }
      if (punchIn != null && punchOut == null) {
        working ??= row;
      } else if (punchOut != null) {
        completed ??= row;
      }
    }

    final source = working ?? completed;
    final status = working != null
        ? 'Punched In'
        : (completed != null || metaPunchedOut > 0
            ? 'Punched Out'
            : (metaPunchedIn > 0 ? 'Punched In' : 'Not Punched In'));
    return TeamAttendancePulse(
      status: status,
      punchInTime: _formatTime(source?['punch_in_time'] ?? earliestIn),
      punchOutTime: _formatTime(source?['punch_out_time'] ?? latestOut),
      workingDuration: _duration(source) ?? '—',
    );
  }

  static String _formatTime(Object? value) {
    if (value == null) return '—';
    if (value is DateTime) {
      return DateFormat('hh:mm a').format(value.toLocal());
    }
    final parsed = DateTime.tryParse('$value');
    if (parsed == null) return '—';
    return DateFormat('hh:mm a').format(parsed.toLocal());
  }

  static String? _duration(Map<String, dynamic>? row) {
    if (row == null) return null;
    final minutes = int.tryParse('${row['total_working_minutes'] ?? ''}');
    if (minutes != null && minutes >= 0) {
      return '${minutes ~/ 60}h ${(minutes % 60).toString().padLeft(2, '0')}m';
    }
    final hours = '${row['working_hours'] ?? ''}'.trim();
    return hours.isEmpty ? null : hours;
  }
}

class _AttendanceStatusCard extends StatefulWidget {
  const _AttendanceStatusCard({
    required this.onDetails,
    required this.punchedIn,
    required this.punchedOut,
    this.pulse,
    this.ownAttendance,
  });

  final TeamAttendancePulse? pulse;
  final Attendance? ownAttendance;
  final int punchedIn;
  final int punchedOut;
  final VoidCallback onDetails;

  @override
  State<_AttendanceStatusCard> createState() => _AttendanceStatusCardState();
}

class _AttendanceStatusCardState extends State<_AttendanceStatusCard> {
  Timer? _timer;
  String _duration = '—';

  @override
  void initState() {
    super.initState();
    _syncTimer();
  }

  @override
  void didUpdateWidget(covariant _AttendanceStatusCard oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.pulse?.livePunchIn != widget.pulse?.livePunchIn ||
        oldWidget.pulse?.workingDuration != widget.pulse?.workingDuration ||
        oldWidget.ownAttendance?.punchIn != widget.ownAttendance?.punchIn ||
        oldWidget.ownAttendance?.punchOut != widget.ownAttendance?.punchOut) {
      _syncTimer();
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _syncTimer() {
    _timer?.cancel();
    final liveFrom = widget.pulse?.livePunchIn;
    if (liveFrom != null) {
      _duration = _liveDuration(liveFrom);
      _timer = Timer.periodic(const Duration(seconds: 1), (_) {
        if (!mounted) return;
        setState(() => _duration = _liveDuration(liveFrom));
      });
      return;
    }
    _duration = widget.pulse?.workingDuration ?? '—';
  }

  String _liveDuration(DateTime punchIn) {
    final elapsed = AttendanceFormat.istNow().difference(punchIn);
    final totalSeconds = elapsed.isNegative ? 0 : elapsed.inSeconds;
    final hours = totalSeconds ~/ 3600;
    final minutes = (totalSeconds % 3600) ~/ 60;
    return '${hours}h ${minutes.toString().padLeft(2, '0')}m';
  }

  @override
  Widget build(BuildContext context) {
    final pulse = widget.pulse;
    final punchedIn = widget.punchedIn;
    final punchedOut = widget.punchedOut;
    final onDetails = widget.onDetails;
    final status = pulse?.status ??
        (punchedIn > punchedOut
            ? 'Punched In'
            : (punchedOut > 0 ? 'Punched Out' : 'Not Punched In'));
    final Color color;
    final IconData icon;
    if (status == 'Punched Out') {
      color = AppColors.success;
      icon = Icons.verified_rounded;
    } else if (status == 'Punched In') {
      color = AppColors.primary;
      icon = Icons.check_circle_rounded;
    } else {
      color = AppColors.warning;
      icon = Icons.fingerprint_rounded;
    }

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(22),
      child: InkWell(
        onTap: onDetails,
        borderRadius: BorderRadius.circular(22),
        child: Ink(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(22),
            boxShadow: const [
              BoxShadow(
                color: Color(0x0F0F172A),
                blurRadius: 18,
                offset: Offset(0, 6),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
            child: Column(
              children: [
                Row(
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        color: color.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Icon(icon, color: color, size: 22),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Attendance Status',
                            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.textSecondary,
                                ),
                          ),
                          Text(
                            status,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                  fontWeight: FontWeight.w800,
                                ),
                          ),
                        ],
                      ),
                    ),
                    TextButton(
                      onPressed: onDetails,
                      child: const Text('View Details'),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    _MetaChip(
                      label: 'Punch In',
                      value: pulse?.punchInTime ?? '—',
                      background: const Color(0xFFE8F1FF),
                    ),
                    const SizedBox(width: 8),
                    _MetaChip(
                      label: 'Punch Out',
                      value: pulse?.punchOutTime ?? '—',
                      background: const Color(0xFFFDE8EF),
                    ),
                    const SizedBox(width: 8),
                    _MetaChip(
                      label: 'Duration',
                      value: _duration,
                      background: const Color(0xFFEDE9FE),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({
    required this.label,
    required this.value,
    required this.background,
  });

  final String label;
  final String value;
  final Color background;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(14),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    fontSize: 10,
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                  ),
            ),
            const SizedBox(height: 4),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SummaryGrid extends StatelessWidget {
  const _SummaryGrid({required this.tiles, required this.onOpen});

  final List<_DashboardTile> tiles;
  final ValueChanged<String> onOpen;

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: tiles.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 1.42,
      ),
      itemBuilder: (context, index) {
        final tile = tiles[index];
        return _SummaryCard(tile: tile, onTap: () => onOpen(tile.path));
      },
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.tile, required this.onTap});

  final _DashboardTile tile;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22),
        child: Ink(
          decoration: BoxDecoration(
            color: tile.background ?? Colors.white,
            borderRadius: BorderRadius.circular(22),
            boxShadow: [
              BoxShadow(
                color: (tile.color).withValues(alpha: 0.08),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 12, 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 38,
                      height: 38,
                      decoration: BoxDecoration(
                        color: tile.color,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: IconTheme(
                        data: const IconThemeData(color: Colors.white, size: 20),
                        child: Center(child: tile.icon),
                      ),
                    ),
                    const Spacer(),
                    Icon(
                      Icons.arrow_forward_ios_rounded,
                      size: 14,
                      color: tile.color.withValues(alpha: 0.7),
                    ),
                  ],
                ),
                const Spacer(),
                Text(
                  tile.value ?? '0',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        height: 1,
                        color: AppColors.textPrimary,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  tile.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ModuleGrid extends StatelessWidget {
  const _ModuleGrid({required this.tiles, required this.onOpen});

  final List<_DashboardTile> tiles;
  final ValueChanged<String> onOpen;

  @override
  Widget build(BuildContext context) {
    final rows = <List<_DashboardTile>>[];
    for (var i = 0; i < tiles.length; i += 2) {
      rows.add(tiles.sublist(i, i + 2 > tiles.length ? tiles.length : i + 2));
    }

    return Column(
      children: [
        for (var i = 0; i < rows.length; i++) ...[
          if (i > 0) const SizedBox(height: 12),
          SizedBox(
            height: 108,
            child: Row(
              children: [
                Expanded(
                  child: _ModuleCard(
                    tile: rows[i][0],
                    onTap: () => onOpen(rows[i][0].path),
                  ),
                ),
                const SizedBox(width: 12),
                if (rows[i].length > 1)
                  Expanded(
                    child: _ModuleCard(
                      tile: rows[i][1],
                      onTap: () => onOpen(rows[i][1].path),
                    ),
                  )
                else
                  const Expanded(child: SizedBox.shrink()),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

class _ModuleCard extends StatelessWidget {
  const _ModuleCard({required this.tile, required this.onTap});

  final _DashboardTile tile;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final tint = tile.background ??
        Color.alphaBlend(tile.color.withValues(alpha: 0.12), Colors.white);
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22),
        child: Ink(
          decoration: BoxDecoration(
            color: tint,
            borderRadius: BorderRadius.circular(22),
            boxShadow: [
              BoxShadow(
                color: tile.color.withValues(alpha: 0.08),
                blurRadius: 14,
                offset: const Offset(0, 5),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 8, 12),
            child: Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: tile.color,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: IconTheme(
                    data: const IconThemeData(color: Colors.white, size: 22),
                    child: Center(child: tile.icon),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        tile.label,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                              height: 1.1,
                            ),
                      ),
                      if (tile.subtitle != null) ...[
                        const SizedBox(height: 2),
                        Text(
                          tile.subtitle!,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                fontSize: 11,
                                height: 1.2,
                                color: AppColors.textSecondary,
                              ),
                        ),
                      ],
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right_rounded, color: AppColors.textMuted, size: 18),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _DashboardTile {
  const _DashboardTile({
    required this.icon,
    required this.label,
    required this.path,
    required this.color,
    this.value,
    this.subtitle,
    this.background,
  });

  final Widget icon;
  final String label;
  final String? value;
  final String? subtitle;
  final String path;
  final Color color;
  final Color? background;
}
