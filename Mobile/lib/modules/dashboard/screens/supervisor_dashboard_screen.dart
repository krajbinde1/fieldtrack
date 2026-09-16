import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/api/api_client.dart';
import '../../../core/auth/user_role.dart';
import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_welcome_card.dart';
import '../../auth/providers/auth_controller.dart';
import '../../manager/api/manager_api.dart';

class SupervisorDashboardScreen extends StatefulWidget {
  const SupervisorDashboardScreen({super.key, required this.auth});
  final AuthController auth;

  @override
  State<SupervisorDashboardScreen> createState() =>
      _SupervisorDashboardScreenState();
}

class _SupervisorDashboardScreenState extends State<SupervisorDashboardScreen> {
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
    if (widget.auth.userRole.isCenterManager ||
        widget.auth.userRole.isProjectHead) {
      try {
        final result = await api.listTeamAttendance();
        pulse = TeamAttendancePulse.fromRows(result.rows, result.meta);
      } catch (_) {}
    }

    return _DashboardSnapshot(data: data, pulse: pulse);
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

  @override
  Widget build(BuildContext context) {
    final role = widget.auth.userRole;
    final session = widget.auth.session;
    final employeeName = session?.employee.fullName.trim() ?? '';
    final userName = session?.user.name?.trim() ?? '';
    final name = employeeName.isNotEmpty
        ? employeeName
        : (userName.isNotEmpty
            ? userName
            : (session?.user.loginId ?? role.label));

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
  });

  final String name;
  final UserRole role;
  final Map<String, dynamic> data;
  final TeamAttendancePulse? pulse;
  final ValueChanged<String> onOpen;

  String get _prefix =>
      role.isAdmin || role.isDirector ? '/director' : '/manager';

  int _count(String key) {
    final value = data[key];
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse('$value') ?? 0;
  }

  List<_DashboardTile> get _summary {
    return [
      _DashboardTile(
        icon: const Icon(Icons.groups_rounded),
        label: 'Employees',
        value: '${_count('employees')}',
        path: role.isCenterManager || role.isProjectHead
            ? '/manager/employees'
            : '$_prefix/team-attendance',
        color: const Color(0xFF2563EB),
      ),
      _DashboardTile(
        icon: const Icon(Icons.fingerprint_rounded),
        label: 'Punched In',
        value: '${_count('punched_in_today')}',
        path: '$_prefix/team-attendance',
        color: const Color(0xFF0F766E),
      ),
      _DashboardTile(
        icon: const Icon(Icons.route_rounded),
        label: 'Active Routes',
        value: '${_count('active_routes')}',
        path: '$_prefix/route-tracking',
        color: const Color(0xFF7C3AED),
      ),
      _DashboardTile(
        icon: const Icon(Icons.event_note_rounded),
        label: 'Pending Leave',
        value: '${_count('pending_leaves')}',
        path: '$_prefix/leaves',
        color: const Color(0xFFD97706),
      ),
      _DashboardTile(
        icon: const Icon(Icons.flag_rounded),
        label: 'Targets',
        value: '${_count('admission_targets')}',
        path: role.isCenterManager || role.isProjectHead
            ? '/manager/admission-targets'
            : '$_prefix/admissions',
        color: const Color(0xFFDB2777),
      ),
    ];
  }

  List<_DashboardTile> get _quickActions {
    if (!role.isCenterManager) return const [];
    return const [
      _DashboardTile(
        icon: Icon(Icons.flag_rounded),
        label: 'Set Admission Targets',
        subtitle: 'Assign weekly or monthly targets',
        path: '/manager/admission-targets/create',
        color: Color(0xFF7C3AED),
      ),
      _DashboardTile(
        icon: Icon(Icons.person_add_alt_1_rounded),
        label: 'Add User',
        subtitle: 'Create staff in your center(s)',
        path: '/manager/employees/create',
        color: Color(0xFF2563EB),
      ),
    ];
  }

  List<_DashboardTile> get _modules {
    return [
      if (role.isCenterManager)
        const _DashboardTile(
          icon: Icon(Icons.groups_rounded),
          label: 'Users / Employees',
          subtitle: 'Staff in assigned center(s)',
          path: '/manager/employees',
          color: Color(0xFF2563EB),
        ),
      _DashboardTile(
        icon: const Icon(Icons.how_to_reg_rounded),
        label: 'Admissions',
        subtitle: 'Review field admissions',
        path: '$_prefix/admissions',
        color: const Color(0xFF0EA5E9),
      ),
      _DashboardTile(
        icon: const Icon(Icons.event_available_rounded),
        label: 'Attendance',
        subtitle: 'Daily team attendance',
        path: '$_prefix/team-attendance',
        color: const Color(0xFF0F766E),
      ),
      _DashboardTile(
        icon: const Icon(Icons.route_rounded),
        label: 'Employee Routes',
        subtitle: 'Live field routes',
        path: '$_prefix/route-tracking',
        color: const Color(0xFF7C3AED),
      ),
      _DashboardTile(
        icon: const Icon(Icons.event_note_rounded),
        label: 'Leave Requests',
        subtitle: 'Approve team leave',
        path: '$_prefix/leaves',
        color: const Color(0xFFD97706),
      ),
      if (role.isCenterManager || role.isProjectHead)
        const _DashboardTile(
          icon: Icon(Icons.analytics_rounded),
          label: 'Reports',
          subtitle: 'Assigned-center reports',
          path: '/manager/reports',
          color: Color(0xFF4F46E5),
        ),
    ];
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.screenPadding,
        AppSpacing.sm,
        AppSpacing.screenPadding,
        AppSpacing.bottomNavHeight + AppSpacing.md,
      ),
      children: [
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
          pulse: pulse,
          punchedIn: _count('punched_in_today'),
          punchedOut: _count('punched_out_today'),
          onDetails: () => onOpen('$_prefix/team-attendance'),
        ),
        const SizedBox(height: AppSpacing.md),
        _SummaryGrid(tiles: _summary, onOpen: onOpen),
        if (_quickActions.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.lg),
          Text(
            'Quick Actions',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: AppSpacing.sm),
          _QuickActionRow(tiles: _quickActions, onOpen: onOpen),
        ],
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
    );
  }
}

class _DashboardSnapshot {
  const _DashboardSnapshot({required this.data, this.pulse});

  final Map<String, dynamic> data;
  final TeamAttendancePulse? pulse;
}

class TeamAttendancePulse {
  const TeamAttendancePulse({
    required this.status,
    required this.punchInTime,
    required this.punchOutTime,
    required this.workingDuration,
  });

  final String status;
  final String punchInTime;
  final String punchOutTime;
  final String workingDuration;

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

class _AttendanceStatusCard extends StatelessWidget {
  const _AttendanceStatusCard({
    required this.onDetails,
    required this.punchedIn,
    required this.punchedOut,
    this.pulse,
  });

  final TeamAttendancePulse? pulse;
  final int punchedIn;
  final int punchedOut;
  final VoidCallback onDetails;

  @override
  Widget build(BuildContext context) {
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
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: onDetails,
        borderRadius: BorderRadius.circular(18),
        child: Ink(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: AppColors.border.withValues(alpha: 0.8)),
            boxShadow: const [
              BoxShadow(
                color: AppColors.shadow,
                blurRadius: 16,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
            child: Column(
              children: [
                Row(
                  children: [
                    Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        color: color.withValues(alpha: 0.12),
                        shape: BoxShape.circle,
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
                const SizedBox(height: 10),
                Row(
                  children: [
                    _MetaChip(
                      label: 'Punch In',
                      value: pulse?.punchInTime ?? '—',
                    ),
                    const SizedBox(width: 8),
                    _MetaChip(
                      label: 'Punch Out',
                      value: pulse?.punchOutTime ?? '—',
                    ),
                    const SizedBox(width: 8),
                    _MetaChip(
                      label: 'Duration',
                      value: pulse?.workingDuration ?? '—',
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
  const _MetaChip({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        decoration: BoxDecoration(
          color: const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    fontSize: 10,
                    color: AppColors.textMuted,
                    fontWeight: FontWeight.w600,
                  ),
            ),
            const SizedBox(height: 2),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    fontWeight: FontWeight.w800,
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
        childAspectRatio: 1.55,
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
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Ink(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: AppColors.border.withValues(alpha: 0.8)),
            boxShadow: const [
              BoxShadow(
                color: AppColors.shadow,
                blurRadius: 12,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 12, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: tile.color.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: IconTheme(
                    data: IconThemeData(color: tile.color, size: 20),
                    child: Center(child: tile.icon),
                  ),
                ),
                const Spacer(),
                Text(
                  tile.value ?? '0',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        height: 1,
                      ),
                ),
                const SizedBox(height: 2),
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

class _QuickActionRow extends StatelessWidget {
  const _QuickActionRow({required this.tiles, required this.onOpen});

  final List<_DashboardTile> tiles;
  final ValueChanged<String> onOpen;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (var i = 0; i < tiles.length; i++) ...[
          if (i > 0) const SizedBox(width: 12),
          Expanded(
            child: _ModuleCard(tile: tiles[i], onTap: () => onOpen(tiles[i].path)),
          ),
        ],
      ],
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
    final tint = Color.alphaBlend(
      tile.color.withValues(alpha: 0.12),
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
            border: Border.all(color: tile.color.withValues(alpha: 0.08)),
            boxShadow: [
              BoxShadow(
                color: tile.color.withValues(alpha: 0.10),
                blurRadius: 12,
                offset: const Offset(0, 4),
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
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: tile.color.withValues(alpha: 0.28),
                        blurRadius: 8,
                        offset: const Offset(0, 3),
                      ),
                    ],
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
  });

  final Widget icon;
  final String label;
  final String? value;
  final String? subtitle;
  final String path;
  final Color color;
}
