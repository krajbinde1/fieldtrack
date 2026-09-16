import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_status_badge.dart';
import '../../auth/providers/auth_controller.dart';
import '../api/leave_api.dart';
import '../models/leave.dart';

class SupervisorLeaveListScreen extends StatefulWidget {
  const SupervisorLeaveListScreen({
    super.key,
    required this.auth,
    required this.apiPrefix,
    this.statusFilter,
    this.centerId,
  });

  final AuthController auth;
  final String apiPrefix;
  final String? statusFilter;
  final int? centerId;

  @override
  State<SupervisorLeaveListScreen> createState() =>
      _SupervisorLeaveListScreenState();
}

class _SupervisorLeaveListScreenState extends State<SupervisorLeaveListScreen> {
  late Future<List<LeaveRecord>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<LeaveRecord>> _load() async {
    return (await LeaveApi.create()).supervisorList(
      widget.apiPrefix,
      centerId: widget.centerId,
      status: widget.statusFilter,
    );
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Leave Requests',
      showBack: true,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<LeaveRecord>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const PgLoadingState();
            }
            if (snapshot.hasError) {
              return PgErrorState(message: '${snapshot.error}', onRetry: _refresh);
            }
            final items = (snapshot.data ?? const <LeaveRecord>[])
                .where((item) => widget.statusFilter == 'pending'
                    ? item.isPending
                    : true)
                .toList();
            if (items.isEmpty) {
              return ListView(
                children: [
                  const SizedBox(height: 80),
                  PgEmptyState(
                    icon: const Icon(Icons.event_available_outlined),
                    message: widget.statusFilter == 'pending'
                        ? 'No pending leave requests in your scope.'
                        : 'No leave requests in your scope.',
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
              itemBuilder: (context, index) {
                final item = items[index];
                return PgCard(
                  onTap: () => context.push(
                    '${widget.apiPrefix == 'director' ? '/director' : '/manager'}/leaves/${item.id}',
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              item.employeeName ?? 'Employee',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ),
                          PgStatusBadge(
                            label: item.statusLabel,
                            tone: item.isApproved
                                ? PgStatusTone.approved
                                : item.isRejected
                                ? PgStatusTone.rejected
                                : PgStatusTone.pending,
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(item.leaveTypeLabel, style: Theme.of(context).textTheme.bodySmall),
                      Text(
                        '${_fmt(item.fromDate)} → ${_fmt(item.toDate)} · ${item.totalDays} day${item.totalDays == 1 ? '' : 's'}',
                        style: Theme.of(context).textTheme.bodySmall,
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

  String _fmt(String raw) {
    final parsed = DateTime.tryParse(raw);
    if (parsed == null) return raw;
    return DateFormat('d MMM yyyy').format(parsed);
  }
}
