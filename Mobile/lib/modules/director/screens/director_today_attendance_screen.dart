import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_status_badge.dart';
import '../../auth/providers/auth_controller.dart';
import '../api/director_api.dart';

class DirectorTodayAttendanceScreen extends StatefulWidget {
  const DirectorTodayAttendanceScreen({
    super.key,
    required this.auth,
    this.centerId,
  });

  final AuthController auth;
  final int? centerId;

  @override
  State<DirectorTodayAttendanceScreen> createState() =>
      _DirectorTodayAttendanceScreenState();
}

class _DirectorTodayAttendanceScreenState
    extends State<DirectorTodayAttendanceScreen> {
  late final DirectorApi _api;
  late Future<DirectorWorkforceResult> _future;

  @override
  void initState() {
    super.initState();
    _api = DirectorApi(
      ApiClient(SessionStore(), onUnauthorized: widget.auth.sessionExpired).dio,
    );
    _future = _api.listWorkforce(centerId: widget.centerId);
  }

  Future<void> _reload() async {
    final next = _api.listWorkforce(centerId: widget.centerId);
    setState(() => _future = next);
    await next;
  }

  List<Map<String, dynamic>> _group(
    List<Map<String, dynamic>> rows,
    String status,
  ) =>
      rows.where((row) => '${row['attendance_status']}' == status).toList();

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Today Attendance',
      showBack: true,
      body: RefreshIndicator(
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
            final rows = snapshot.data?.rows ?? const <Map<String, dynamic>>[];
            final punchedIn = _group(rows, 'punched_in');
            final punchedOut = _group(rows, 'punched_out');
            final notIn = _group(rows, 'not_punched_in');

            return ListView(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              children: [
                _SummaryRow(
                  punchedIn: punchedIn.length + punchedOut.length,
                  total: rows.length,
                  punchedOut: punchedOut.length,
                  notIn: notIn.length,
                ),
                const SizedBox(height: 16),
                _Section(
                  title: 'Punched In',
                  color: const Color(0xFF0F766E),
                  items: punchedIn,
                  empty: 'Nobody is currently punched in.',
                ),
                const SizedBox(height: 16),
                _Section(
                  title: 'Punched Out',
                  color: const Color(0xFF2563EB),
                  items: punchedOut,
                  empty: 'Nobody has punched out yet.',
                ),
                const SizedBox(height: 16),
                _Section(
                  title: 'Not Punched In',
                  color: const Color(0xFFEA580C),
                  items: notIn,
                  empty: 'Everyone applicable has punched in.',
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({
    required this.punchedIn,
    required this.total,
    required this.punchedOut,
    required this.notIn,
  });

  final int punchedIn;
  final int total;
  final int punchedOut;
  final int notIn;

  @override
  Widget build(BuildContext context) {
    return PgCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Punched In Today',
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w600,
                ),
          ),
          Text(
            '$punchedIn / $total',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: 8),
          Text(
            'Punched Out $punchedOut · Not Punched In $notIn',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({
    required this.title,
    required this.color,
    required this.items,
    required this.empty,
  });

  final String title;
  final Color color;
  final List<Map<String, dynamic>> items;
  final String empty;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          '$title (${items.length})',
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
                color: color,
              ),
        ),
        const SizedBox(height: 8),
        if (items.isEmpty)
          PgEmptyState(message: empty, icon: const Icon(Icons.people_outline))
        else
          for (final item in items) ...[
            PgCard(
              margin: const EdgeInsets.only(bottom: 10),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${item['full_name'] ?? 'Employee'}',
                          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                        Text(
                          '${item['role_label'] ?? '—'} · ${item['center_name'] ?? '—'}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                        Text(
                          '${item['employee_code'] ?? '—'} / ${item['login_id'] ?? '—'}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ),
                  PgStatusBadge(
                    label: '${item['today_attendance_status'] ?? title}',
                    tone: title == 'Not Punched In'
                        ? PgStatusTone.pending
                        : (title == 'Punched Out'
                            ? PgStatusTone.info
                            : PgStatusTone.approved),
                  ),
                ],
              ),
            ),
          ],
      ],
    );
  }
}
