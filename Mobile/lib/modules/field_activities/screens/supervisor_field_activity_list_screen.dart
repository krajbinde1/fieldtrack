import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../auth/providers/auth_controller.dart';
import '../api/field_activity_api.dart';
import '../models/field_activity.dart';
import 'field_activity_list_screen.dart';

class SupervisorFieldActivityListScreen extends StatefulWidget {
  const SupervisorFieldActivityListScreen({
    super.key,
    required this.auth,
    required this.apiPrefix,
    this.title = 'Field Activities',
    this.centerId,
    this.initialPeriod = 'today',
  });

  final AuthController auth;
  final String apiPrefix;
  final String title;
  final int? centerId;
  final String initialPeriod;

  @override
  State<SupervisorFieldActivityListScreen> createState() =>
      _SupervisorFieldActivityListScreenState();
}

class _SupervisorFieldActivityListScreenState
    extends State<SupervisorFieldActivityListScreen> {
  late String _period;
  int? _employeeId;
  DateTime? _from;
  DateTime? _to;
  late Future<FieldActivityListResult> _future;

  @override
  void initState() {
    super.initState();
    _period = widget.initialPeriod.isEmpty ? 'today' : widget.initialPeriod;
    _future = _load();
  }

  Future<FieldActivityListResult> _load() async {
    return (await FieldActivityApi.create()).supervisorList(
      widget.apiPrefix,
      centerId: widget.centerId,
      employeeId: _employeeId,
      period: _period,
      dateFrom: _from == null ? null : DateFormat('yyyy-MM-dd').format(_from!),
      dateTo: _to == null ? null : DateFormat('yyyy-MM-dd').format(_to!),
    );
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _setPeriod(String period) async {
    if (period == 'custom') {
      final range = await showDateRangePicker(
        context: context,
        firstDate: DateTime(2020),
        lastDate: DateTime.now().add(const Duration(days: 1)),
        initialDateRange: _from != null && _to != null
            ? DateTimeRange(start: _from!, end: _to!)
            : DateTimeRange(
                start: DateTime.now().subtract(const Duration(days: 7)),
                end: DateTime.now(),
              ),
      );
      if (range == null) return;
      setState(() {
        _period = 'custom';
        _from = range.start;
        _to = range.end;
        _future = _load();
      });
      return;
    }
    setState(() {
      _period = period;
      _from = null;
      _to = null;
      _future = _load();
    });
  }

  String get _detailPrefix =>
      widget.apiPrefix == 'director' ? '/director' : '/manager';

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: widget.title,
      showBack: true,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<FieldActivityListResult>(
          future: _future,
          builder: (context, snapshot) {
            final employees = snapshot.data?.employees ?? const <FieldActivityEmployeeOption>[];
            final filters = _Filters(
              period: _period,
              employeeId: _employeeId,
              employees: employees,
              customLabel: _from != null && _to != null
                  ? '${DateFormat('d MMM').format(_from!)} – ${DateFormat('d MMM').format(_to!)}'
                  : 'Custom Date',
              onPeriod: _setPeriod,
              onEmployee: (value) {
                setState(() {
                  _employeeId = value;
                  _future = _load();
                });
              },
            );

            if (snapshot.connectionState != ConnectionState.done) {
              return ListView(
                children: [
                  filters,
                  const SizedBox(height: 80),
                  const PgLoadingState(),
                ],
              );
            }
            if (snapshot.hasError) {
              return ListView(
                children: [
                  filters,
                  PgErrorState(message: '${snapshot.error}', onRetry: _refresh),
                ],
              );
            }
            final items = snapshot.data?.items ?? const <FieldActivity>[];
            if (items.isEmpty) {
              return ListView(
                children: [
                  filters,
                  const SizedBox(height: 40),
                  const PgEmptyState(
                    icon: Icon(Icons.photo_camera_outlined),
                    message: 'No field activities in this period.',
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.only(bottom: AppSpacing.screenPadding),
              itemCount: items.length + 1,
              separatorBuilder: (_, index) =>
                  index == 0 ? const SizedBox.shrink() : const SizedBox(height: AppSpacing.md),
              itemBuilder: (context, index) {
                if (index == 0) return filters;
                final activity = items[index - 1];
                return Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: AppSpacing.screenPadding,
                  ),
                  child: FieldActivityCard(
                    activity: activity,
                    showEmployee: true,
                    onOpen: () => context.push(
                      '$_detailPrefix/field-activities/${activity.id}',
                    ),
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

class _Filters extends StatelessWidget {
  const _Filters({
    required this.period,
    required this.employeeId,
    required this.employees,
    required this.customLabel,
    required this.onPeriod,
    required this.onEmployee,
  });

  final String period;
  final int? employeeId;
  final List<FieldActivityEmployeeOption> employees;
  final String customLabel;
  final ValueChanged<String> onPeriod;
  final ValueChanged<int?> onEmployee;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.screenPadding,
        AppSpacing.screenPadding,
        AppSpacing.screenPadding,
        AppSpacing.md,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Chip(
                label: 'Today',
                selected: period == 'today',
                onTap: () => onPeriod('today'),
              ),
              _Chip(
                label: 'This Week',
                selected: period == 'this_week',
                onTap: () => onPeriod('this_week'),
              ),
              _Chip(
                label: 'This Month',
                selected: period == 'this_month',
                onTap: () => onPeriod('this_month'),
              ),
              _Chip(
                label: customLabel,
                selected: period == 'custom',
                onTap: () => onPeriod('custom'),
              ),
            ],
          ),
          if (employees.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.md),
            DropdownButtonFormField<int?>(
              key: ValueKey(employeeId),
              initialValue: employeeId,
              decoration: const InputDecoration(labelText: 'Employee'),
              items: [
                const DropdownMenuItem<int?>(
                  value: null,
                  child: Text('All Employees'),
                ),
                for (final employee in employees)
                  DropdownMenuItem<int?>(
                    value: employee.id,
                    child: Text(employee.name),
                  ),
              ],
              onChanged: onEmployee,
            ),
          ],
        ],
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onTap(),
      selectedColor: AppColors.primary.withValues(alpha: 0.16),
      labelStyle: TextStyle(
        color: selected ? AppColors.primary : AppColors.textSecondary,
        fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
      ),
    );
  }
}
