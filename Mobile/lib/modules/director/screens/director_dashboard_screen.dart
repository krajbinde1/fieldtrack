import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/api/api_client.dart';
import '../../../core/auth/user_role.dart';
import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/routing/center_scope.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_welcome_card.dart';
import '../../attendance/models/attendance.dart';
import '../../attendance/providers/attendance_provider.dart';
import '../../attendance/widgets/attendance_status_card.dart';
import '../../auth/providers/auth_controller.dart';

class DirectorDashboardScreen extends ConsumerStatefulWidget {
  const DirectorDashboardScreen({super.key, required this.auth});

  final AuthController auth;

  @override
  ConsumerState<DirectorDashboardScreen> createState() =>
      _DirectorDashboardScreenState();
}

class _DirectorDashboardScreenState
    extends ConsumerState<DirectorDashboardScreen> {
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
      final response = await dio.get('/dashboard');
      final body = response.data;
      if (body is Map && body['data'] is Map) {
        return Map<String, dynamic>.from(body['data'] as Map);
      }
    } catch (_) {}
    return const {};
  }

  Future<void> _refresh() async {
    if (widget.auth.userRole.isProjectHead) {
      ref.invalidate(todayAttendanceProvider);
    }
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  Future<void> _open(String path) async {
    await context.push(path);
    if (!mounted) return;
    if (widget.auth.userRole.isProjectHead && path.startsWith('/attendance')) {
      await ref.read(todayAttendanceProvider.notifier).refresh();
    }
    await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    final session = widget.auth.session;
    final displayName = session?.displayName.trim() ?? '';
    final name = displayName.isNotEmpty ? displayName : widget.auth.userRole.label;
    final ownAttendance = widget.auth.userRole.isProjectHead
        ? ref.watch(todayAttendanceProvider).maybeWhen(
              data: (value) => value,
              orElse: () => null,
            )
        : null;

    return PgPageScaffold(
      auth: widget.auth,
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          return RefreshIndicator(
            onRefresh: _refresh,
            child: DirectorDashboardView(
              name: name,
              role: widget.auth.userRole,
              data: snapshot.data ?? const {},
              ownAttendance: ownAttendance,
              onOpen: _open,
            ),
          );
        },
      ),
    );
  }
}

class DirectorDashboardView extends StatelessWidget {
  const DirectorDashboardView({
    super.key,
    required this.name,
    required this.role,
    required this.data,
    required this.onOpen,
    this.ownAttendance,
    this.centerId,
    this.centerName,
    this.centerManagerName,
    this.schemeName,
  });

  final String name;
  final UserRole role;
  final Map<String, dynamic> data;
  final Attendance? ownAttendance;
  final int? centerId;
  final String? centerName;
  final String? centerManagerName;
  final String? schemeName;
  final ValueChanged<String> onOpen;

  bool get _centerScoped => centerId != null;

  bool get _showSelfAttendance => role.isProjectHead && !_centerScoped;

  String _path(String path) => withCenterId(path, centerId);

  int _count(String key) {
    final value = data[key];
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse('$value') ?? 0;
  }

  List<_DirectorTile> get _tiles {
    final employees = _count('employees');
    final punchedIn = _count('punched_in_today');
    final pendingLeave = _count('pending_project_head_leaves') > 0
        ? _count('pending_project_head_leaves')
        : _count('pending_leaves');
    final confirmed = _count('confirmed_admissions');
    final confirmedFromCounts = data['admission_counts'];
    final confirmedCount = confirmed > 0
        ? confirmed
        : (confirmedFromCounts is Map
            ? int.tryParse('${confirmedFromCounts['confirmed'] ?? 0}') ?? 0
            : 0);

    return [
      if (!_centerScoped)
        _DirectorTile(
          icon: const Icon(Icons.apartment_rounded),
          label: 'Total Centers',
          value:
              '${_count('active_centers') == 0 && _count('centers') > 0 ? _count('centers') : _count('active_centers')}',
          path: '/director/centers',
          color: const Color(0xFF0369A1),
          background: const Color(0xFFE0F2FE),
        ),
      _DirectorTile(
        icon: const Icon(Icons.groups_rounded),
        label: 'Employees',
        value: '$employees',
        path: _path('/director/employees'),
        color: const Color(0xFF2563EB),
        background: const Color(0xFFE8F1FF),
      ),
      _DirectorTile(
        icon: const Icon(Icons.fingerprint_rounded),
        label: 'Punched In Today',
        value: '$punchedIn / $employees',
        path: _path('/director/team-attendance'),
        color: const Color(0xFF0F766E),
        background: const Color(0xFFE6F7F1),
      ),
      _DirectorTile(
        icon: const Icon(Icons.route_rounded),
        label: 'Active Routes',
        value: '${_count('active_routes')}',
        path: _path('/director/route-tracking?active=1'),
        color: const Color(0xFF7C3AED),
        background: const Color(0xFFF0E9FF),
      ),
      if (!_centerScoped && (role.isDirector || role.isAdmin))
        _DirectorTile(
          icon: const Icon(Icons.event_note_rounded),
          label: 'Project Head Leave',
          value: 'Pending: $pendingLeave',
          path: '/director/leaves?status=pending',
          color: const Color(0xFFEA580C),
          background: const Color(0xFFFFF6E5),
        ),
      _DirectorTile(
        icon: const Icon(Icons.how_to_reg_rounded),
        label: 'Confirmed Admissions',
        value: 'Total: $confirmedCount',
        path: _centerScoped
            ? _path('/director/admissions?status=confirmed')
            : '/director/admissions',
        color: const Color(0xFF0EA5E9),
        background: const Color(0xFFE0F4FF),
      ),
      _DirectorTile(
        icon: const Icon(Icons.photo_camera_outlined),
        label: 'Field Activities Today',
        value: '${_count('field_activities_today')}',
        path: _path('/director/field-activities?period=today'),
        color: const Color(0xFF0F766E),
        background: const Color(0xFFE6F7F1),
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
          _centerScoped
              ? AppSpacing.xl
              : AppSpacing.bottomNavHeight + AppSpacing.md,
        ),
        children: [
          if (_centerScoped)
            _DirectorCenterHeader(
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
          if (_showSelfAttendance) ...[
            AttendanceStatusCard(
              pulse: TeamAttendancePulse.fromOwn(ownAttendance),
              ownAttendance: ownAttendance,
              punchedIn: ownAttendance?.punchIn != null ? 1 : 0,
              punchedOut: ownAttendance?.punchOut != null ? 1 : 0,
              onDetails: () => onOpen('/attendance'),
            ),
            const SizedBox(height: 12),
          ],
          _DirectorSummaryGrid(tiles: _tiles, onOpen: onOpen),
        ],
      ),
    );
  }
}

class _DirectorCenterHeader extends StatelessWidget {
  const _DirectorCenterHeader({
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

class _DirectorSummaryGrid extends StatelessWidget {
  const _DirectorSummaryGrid({required this.tiles, required this.onOpen});

  final List<_DirectorTile> tiles;
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
        childAspectRatio: 1.22,
      ),
      itemBuilder: (context, index) {
        final tile = tiles[index];
        return _DirectorSummaryCard(tile: tile, onTap: () => onOpen(tile.path));
      },
    );
  }
}

class _DirectorSummaryCard extends StatelessWidget {
  const _DirectorSummaryCard({required this.tile, required this.onTap});

  final _DirectorTile tile;
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
            color: tile.background,
            borderRadius: BorderRadius.circular(22),
            boxShadow: [
              BoxShadow(
                color: tile.color.withValues(alpha: 0.08),
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
                  tile.value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                        height: 1.1,
                        color: AppColors.textPrimary,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  tile.label,
                  maxLines: 2,
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

class _DirectorTile {
  const _DirectorTile({
    required this.icon,
    required this.label,
    required this.value,
    required this.path,
    required this.color,
    required this.background,
  });

  final Widget icon;
  final String label;
  final String value;
  final String path;
  final Color color;
  final Color background;
}
