import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/api/api_client.dart';
import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../admissions/api/admission_api.dart';
import '../../admissions/models/admission.dart';
import '../../auth/providers/auth_controller.dart';
import '../api/director_api.dart';

class DirectorConfirmedAdmissionsSummaryScreen extends StatefulWidget {
  const DirectorConfirmedAdmissionsSummaryScreen({
    super.key,
    required this.auth,
    this.centerId,
  });

  final AuthController auth;
  final int? centerId;

  @override
  State<DirectorConfirmedAdmissionsSummaryScreen> createState() =>
      _DirectorConfirmedAdmissionsSummaryScreenState();
}

class _DirectorConfirmedAdmissionsSummaryScreenState
    extends State<DirectorConfirmedAdmissionsSummaryScreen> {
  late final DirectorApi _api;
  late Future<List<Map<String, dynamic>>> _future;
  int? _schemeId;
  int? _centerId;

  @override
  void initState() {
    super.initState();
    _centerId = widget.centerId;
    _api = DirectorApi(
      ApiClient(SessionStore(), onUnauthorized: widget.auth.sessionExpired).dio,
    );
    _future = _load();
  }

  Future<List<Map<String, dynamic>>> _load() => _api.listConfirmedByCenter(
        schemeId: _schemeId,
        centerId: _centerId,
      );

  Future<void> _reload() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  @override
  Widget build(BuildContext context) {
    if (widget.centerId != null) {
      return DirectorConfirmedAdmissionsListScreen(
        auth: widget.auth,
        centerId: widget.centerId,
        centerName: 'Center',
      );
    }

    return PgPageScaffold(
      title: 'Confirmed Admissions',
      showBack: true,
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<List<Map<String, dynamic>>>(
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
            final items = snapshot.data ?? const <Map<String, dynamic>>[];
            if (items.isEmpty) {
              return ListView(
                children: const [
                  SizedBox(height: 80),
                  PgEmptyState(
                    icon: Icon(Icons.how_to_reg_outlined),
                    message: 'No confirmed admissions in Director scope.',
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final item = items[index];
                final centerId = int.tryParse('${item['center_id'] ?? 0}') ?? 0;
                return PgCard(
                  onTap: centerId > 0
                      ? () => context.push(
                            '/director/admissions?status=confirmed&center_id=$centerId',
                            extra: item,
                          )
                      : null,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${item['center_name'] ?? 'Center'}',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Scheme / Project: ${item['scheme_name'] ?? item['project_name'] ?? '—'}',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: AppColors.textSecondary,
                            ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Total Confirmed Admissions: ${item['total_confirmed'] ?? 0}',
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                              color: const Color(0xFF0EA5E9),
                            ),
                      ),
                    ],
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}

class DirectorConfirmedAdmissionsListScreen extends StatefulWidget {
  const DirectorConfirmedAdmissionsListScreen({
    super.key,
    required this.auth,
    this.centerId,
    this.centerName,
    this.schemeId,
  });

  final AuthController auth;
  final int? centerId;
  final String? centerName;
  final int? schemeId;

  @override
  State<DirectorConfirmedAdmissionsListScreen> createState() =>
      _DirectorConfirmedAdmissionsListScreenState();
}

class _DirectorConfirmedAdmissionsListScreenState
    extends State<DirectorConfirmedAdmissionsListScreen> {
  AdmissionApi? _api;
  late Future<List<AdmissionRecord>> _future;
  int? _schemeId;
  int? _centerId;
  int? _employeeId;
  String? _employeeName;
  DateTime? _from;
  DateTime? _to;

  @override
  void initState() {
    super.initState();
    _schemeId = widget.schemeId;
    _centerId = widget.centerId;
    _future = _load();
  }

  Future<List<AdmissionRecord>> _load() async {
    _api ??= await AdmissionApi.create();
    return _api!.supervisorList(
      'director',
      status: 'confirmed',
      centerId: _centerId,
      schemeId: _schemeId,
      employeeId: _employeeId,
      confirmedFrom: _from == null ? null : DateFormat('yyyy-MM-dd').format(_from!),
      confirmedTo: _to == null ? null : DateFormat('yyyy-MM-dd').format(_to!),
    );
  }

  Future<void> _reload() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  Future<void> _pickRange() async {
    final now = DateTime.now();
    final range = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2020),
      lastDate: now.add(const Duration(days: 1)),
      initialDateRange: _from != null && _to != null
          ? DateTimeRange(start: _from!, end: _to!)
          : null,
    );
    if (range == null) return;
    setState(() {
      _from = range.start;
      _to = range.end;
    });
    await _reload();
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: widget.centerName == null || widget.centerName!.isEmpty
          ? 'Confirmed Admissions'
          : widget.centerName!,
      showBack: true,
      actions: [
        IconButton(
          tooltip: 'Date range',
          onPressed: _pickRange,
          icon: const Icon(Icons.date_range_rounded),
        ),
      ],
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<List<AdmissionRecord>>(
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
            final items = snapshot.data ?? const <AdmissionRecord>[];
            if (items.isEmpty) {
              return ListView(
                children: const [
                  SizedBox(height: 80),
                  PgEmptyState(
                    icon: Icon(Icons.how_to_reg_outlined),
                    message: 'No confirmed admissions for this center.',
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              itemCount: items.length + 1,
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                if (index == 0) {
                    return _ListFilters(
                    items: items,
                    schemeId: _schemeId,
                    employeeName: _employeeName,
                    onScheme: (value) {
                      setState(() => _schemeId = value);
                      _reload();
                    },
                    onEmployeeName: (name) {
                      if (name == null) {
                        setState(() {
                          _employeeName = null;
                          _employeeId = null;
                        });
                        _reload();
                        return;
                      }
                      AdmissionRecord? match;
                      for (final item in items) {
                        if (item.employeeName == name) {
                          match = item;
                          break;
                        }
                      }
                      setState(() {
                        _employeeName = name;
                        _employeeId = match?.employeeId;
                      });
                      _reload();
                    },
                  );
                }
                final item = items[index - 1];
                return PgCard(
                  onTap: () => context.push('/director/admissions/${item.id}'),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.displayName.isEmpty ? 'Admission #${item.id}' : item.displayName,
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                      const SizedBox(height: 6),
                      Text('Employee / Mobilizer: ${item.employeeName ?? '—'}'),
                      Text('Scheme / Project: ${item.schemeName ?? item.projectName ?? '—'}'),
                      Text('District: ${item.districtName ?? '—'}'),
                      Text('Taluka: ${item.talukaName ?? '—'}'),
                      Text(
                        'Confirmed Date: ${item.confirmedAt == null ? '—' : _fmt(item.confirmedAt!)}',
                      ),
                      Text('Confirmed By: ${item.confirmedByName ?? '—'}'),
                    ],
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }

  String _fmt(String raw) {
    final parsed = DateTime.tryParse(raw);
    if (parsed == null) return raw;
    return DateFormat('d MMM yyyy').format(parsed);
  }
}

class _ListFilters extends StatelessWidget {
  const _ListFilters({
    required this.items,
    required this.schemeId,
    required this.employeeName,
    required this.onScheme,
    required this.onEmployeeName,
  });

  final List<AdmissionRecord> items;
  final int? schemeId;
  final String? employeeName;
  final ValueChanged<int?> onScheme;
  final ValueChanged<String?> onEmployeeName;

  @override
  Widget build(BuildContext context) {
    final schemes = <int, String>{};
    final employees = <String>{};
    for (final item in items) {
      if (item.schemeId != null && (item.schemeName ?? '').isNotEmpty) {
        schemes[item.schemeId!] = item.schemeName!;
      }
      if ((item.employeeName ?? '').trim().isNotEmpty) {
        employees.add(item.employeeName!.trim());
      }
    }
    return Column(
      children: [
        DropdownButtonFormField<int>(
          // ignore: deprecated_member_use
          value: schemeId,
          decoration: const InputDecoration(labelText: 'Scheme / Project'),
          items: [
            const DropdownMenuItem(value: null, child: Text('All schemes')),
            for (final entry in schemes.entries)
              DropdownMenuItem(value: entry.key, child: Text(entry.value)),
          ],
          onChanged: onScheme,
        ),
        const SizedBox(height: 8),
        DropdownButtonFormField<String>(
          // ignore: deprecated_member_use
          value: employeeName,
          decoration: const InputDecoration(labelText: 'Employee / Mobilizer'),
          items: [
            const DropdownMenuItem(value: null, child: Text('All employees')),
            for (final name in employees)
              DropdownMenuItem(value: name, child: Text(name)),
          ],
          onChanged: onEmployeeName,
        ),
      ],
    );
  }
}
