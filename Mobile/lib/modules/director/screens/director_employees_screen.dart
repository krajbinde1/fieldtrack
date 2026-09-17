import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_status_badge.dart';
import '../../auth/providers/auth_controller.dart';
import '../api/director_api.dart';

class DirectorEmployeesScreen extends StatefulWidget {
  const DirectorEmployeesScreen({
    super.key,
    required this.auth,
    this.centerId,
  });

  final AuthController auth;
  final int? centerId;

  @override
  State<DirectorEmployeesScreen> createState() => _DirectorEmployeesScreenState();
}

class _DirectorEmployeesScreenState extends State<DirectorEmployeesScreen> {
  late final DirectorApi _api;
  late Future<DirectorWorkforceResult> _future;
  final _search = TextEditingController();
  String? _role;
  int? _schemeId;
  int? _centerId;
  String? _attendanceStatus;

  @override
  void initState() {
    super.initState();
    _centerId = widget.centerId;
    _api = DirectorApi(
      ApiClient(SessionStore(), onUnauthorized: widget.auth.sessionExpired).dio,
    );
    _future = _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<DirectorWorkforceResult> _load() => _api.listWorkforce(
        centerId: _centerId,
        schemeId: _schemeId,
        role: _role,
        attendanceStatus: _attendanceStatus,
        search: _search.text.trim(),
      );

  Future<void> _reload() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Employees',
      showBack: true,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.screenPadding,
              AppSpacing.sm,
              AppSpacing.screenPadding,
              0,
            ),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _reload(),
              decoration: InputDecoration(
                hintText: 'Search name, code or login ID',
                prefixIcon: const Icon(Icons.search_rounded),
                filled: true,
                fillColor: Colors.white,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: BorderSide.none,
                ),
              ),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _reload,
              child: FutureBuilder<DirectorWorkforceResult>(
                future: _future,
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done) {
                    return const PgLoadingState();
                  }
                  if (snapshot.hasError) {
                    return PgErrorState(
                      message: '${snapshot.error}',
                      onRetry: _reload,
                    );
                  }
                  final result = snapshot.data;
                  final items = result?.rows ?? const <Map<String, dynamic>>[];
                  return ListView(
                    padding: const EdgeInsets.all(AppSpacing.screenPadding),
                    children: [
                      _FilterBar(
                        result: result,
                        role: _role,
                        schemeId: _schemeId,
                        centerId: _centerId,
                        attendanceStatus: _attendanceStatus,
                        lockCenter: widget.centerId != null,
                        onChanged: (role, schemeId, centerId, attendance) {
                          setState(() {
                            _role = role;
                            _schemeId = schemeId;
                            _centerId = widget.centerId ?? centerId;
                            _attendanceStatus = attendance;
                          });
                          _reload();
                        },
                      ),
                      const SizedBox(height: 12),
                      if (items.isEmpty)
                        const Padding(
                          padding: EdgeInsets.only(top: 48),
                          child: PgEmptyState(
                            icon: Icon(Icons.groups_outlined),
                            message: 'No employees found in Director scope.',
                          ),
                        )
                      else
                        for (final item in items) ...[
                          _EmployeeCard(item: item),
                          const SizedBox(height: 12),
                        ],
                    ],
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterBar extends StatelessWidget {
  const _FilterBar({
    required this.result,
    required this.role,
    required this.schemeId,
    required this.centerId,
    required this.attendanceStatus,
    required this.lockCenter,
    required this.onChanged,
  });

  final DirectorWorkforceResult? result;
  final String? role;
  final int? schemeId;
  final int? centerId;
  final String? attendanceStatus;
  final bool lockCenter;
  final void Function(String? role, int? schemeId, int? centerId, String? attendance)
      onChanged;

  List<Map<String, dynamic>> _options(String key) {
    final raw = result?.meta[key];
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    final roles = _options('roles');
    final schemes = _options('schemes');
    final centers = _options('centers');
    final statuses = _options('attendance_statuses');

    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: _Dropdown(
                label: 'Role',
                value: role,
                items: [
                  for (final item in roles)
                    (item['value']?.toString() ?? '', item['label']?.toString() ?? ''),
                ],
                onChanged: (value) => onChanged(value, schemeId, centerId, attendanceStatus),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _Dropdown(
                label: 'Scheme / Project',
                value: schemeId?.toString(),
                items: [
                  for (final item in schemes)
                    ('${item['id']}', item['name']?.toString() ?? ''),
                ],
                onChanged: (value) => onChanged(
                  role,
                  int.tryParse(value ?? ''),
                  centerId,
                  attendanceStatus,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(
              child: _Dropdown(
                label: 'Center',
                value: centerId?.toString(),
                enabled: !lockCenter,
                items: [
                  for (final item in centers)
                    ('${item['id']}', item['name']?.toString() ?? ''),
                ],
                onChanged: (value) => onChanged(
                  role,
                  schemeId,
                  int.tryParse(value ?? ''),
                  attendanceStatus,
                ),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _Dropdown(
                label: 'Attendance',
                value: attendanceStatus,
                items: [
                  for (final item in statuses)
                    (
                      item['value']?.toString() ?? '',
                      item['label']?.toString() ?? '',
                    ),
                ],
                onChanged: (value) =>
                    onChanged(role, schemeId, centerId, value),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _Dropdown extends StatelessWidget {
  const _Dropdown({
    required this.label,
    required this.items,
    required this.onChanged,
    this.value,
    this.enabled = true,
  });

  final String label;
  final String? value;
  final List<(String, String)> items;
  final ValueChanged<String?> onChanged;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<String>(
      // ignore: deprecated_member_use
      value: value,
      isExpanded: true,
      decoration: InputDecoration(
        labelText: label,
        isDense: true,
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide.none,
        ),
      ),
      items: [
        const DropdownMenuItem(value: null, child: Text('All')),
        for (final item in items)
          if (item.$1.isNotEmpty)
            DropdownMenuItem(value: item.$1, child: Text(item.$2)),
      ],
      onChanged: enabled ? onChanged : null,
    );
  }
}

class _EmployeeCard extends StatelessWidget {
  const _EmployeeCard({required this.item});

  final Map<String, dynamic> item;

  String _text(String key, {String fallback = '—'}) {
    final value = '${item[key] ?? ''}'.trim();
    return value.isEmpty ? fallback : value;
  }

  PgStatusTone get _tone {
    final status = _text('attendance_status');
    if (status == 'punched_in') return PgStatusTone.approved;
    if (status == 'punched_out') return PgStatusTone.info;
    return PgStatusTone.pending;
  }

  @override
  Widget build(BuildContext context) {
    return PgCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  _text('full_name', fallback: 'Employee'),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
              ),
              PgStatusBadge(
                label: _text('today_attendance_status', fallback: 'Not Punched In'),
                tone: _tone,
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            'Code / Login: ${_text('employee_code')} / ${_text('login_id')}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          Text(
            'Role: ${_text('role_label', fallback: _text('staff_role_label'))}',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          Text(
            'Scheme / Project: ${_text('scheme_name', fallback: _text('project_name'))}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          Text(
            'Center: ${_text('center_name')}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}
