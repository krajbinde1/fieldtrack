import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/api/api_client.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/storage/session_store.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_status_badge.dart';
import '../../auth/providers/auth_controller.dart';
import '../api/manager_api.dart';

class ManagerAdmissionTargetsScreen extends StatefulWidget {
  const ManagerAdmissionTargetsScreen({super.key, required this.auth});

  final AuthController auth;

  @override
  State<ManagerAdmissionTargetsScreen> createState() =>
      _ManagerAdmissionTargetsScreenState();
}

class _ManagerAdmissionTargetsScreenState
    extends State<ManagerAdmissionTargetsScreen> {
  late final ManagerApi _api;
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();
    _api = ManagerApi(
      ApiClient(SessionStore(), onUnauthorized: widget.auth.sessionExpired).dio,
    );
    _future = _api.listAdmissionTargets();
  }

  Future<void> _refresh() async {
    setState(() => _future = _api.listAdmissionTargets());
    await _future;
  }

  String _fmt(Object? value) {
    final parsed = DateTime.tryParse('$value');
    if (parsed == null) return value == null ? '—' : '$value';
    return DateFormat('d MMM yyyy').format(parsed);
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Admission Targets',
      showBack: true,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<Map<String, dynamic>>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const PgLoadingState();
            }
            if (snapshot.hasError) {
              return PgErrorState(
                message: '${snapshot.error}',
                onRetry: _refresh,
              );
            }
            final items = snapshot.data ?? const <Map<String, dynamic>>[];
            if (items.isEmpty) {
              return ListView(
                children: const [
                  SizedBox(height: 80),
                  PgEmptyState(
                    icon: Icon(Icons.flag_outlined),
                    message: 'No admission targets in your assigned center(s).',
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
              itemBuilder: (context, index) {
                final row = items[index];
                final employee = row['employee'];
                final center = row['center'];
                final employeeName = employee is Map
                    ? '${employee['full_name'] ?? 'Employee'}'
                    : 'Employee';
                final centerName =
                    center is Map ? '${center['name'] ?? ''}' : '';
                return PgCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              employeeName,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ),
                          PgStatusBadge(
                            label: '${row['target_type_label'] ?? row['target_type'] ?? 'Target'}',
                            tone: PgStatusTone.info,
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Target: ${row['target_count'] ?? 0}',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      Text(
                        '${_fmt(row['period_start'])} → ${_fmt(row['period_end'])}'
                        '${centerName.isEmpty ? '' : ' · $centerName'}',
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
}
