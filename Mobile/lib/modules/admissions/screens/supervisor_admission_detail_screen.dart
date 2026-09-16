import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_status_badge.dart';
import '../../auth/providers/auth_controller.dart';
import '../api/admission_api.dart';
import '../models/admission.dart';

class SupervisorAdmissionDetailScreen extends StatefulWidget {
  const SupervisorAdmissionDetailScreen({
    super.key,
    required this.auth,
    required this.apiPrefix,
    required this.admissionId,
  });

  final AuthController auth;
  final String apiPrefix;
  final int admissionId;

  @override
  State<SupervisorAdmissionDetailScreen> createState() =>
      _SupervisorAdmissionDetailScreenState();
}

class _SupervisorAdmissionDetailScreenState
    extends State<SupervisorAdmissionDetailScreen> {
  late Future<AdmissionRecord> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<AdmissionRecord> _load() async {
    return (await AdmissionApi.create())
        .supervisorShow(widget.apiPrefix, widget.admissionId);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Admission',
      showBack: true,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<AdmissionRecord>(
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
            final item = snapshot.data;
            if (item == null) {
              return const PgEmptyState(
                icon: Icon(Icons.how_to_reg_rounded),
                message: 'Admission not found.',
              );
            }
            final rows = <(String, String)>[
              ('Status', item.statusLabel),
              ('Employee', item.employeeName ?? '—'),
              ('Center', item.centerName ?? '—'),
              ('Scheme / Project', item.schemeName ?? item.projectName ?? '—'),
              ('Gender', item.gender ?? '—'),
              ('Address', item.addressLabel.isEmpty ? '—' : item.addressLabel),
              (
                'Submitted',
                item.submittedAt == null ? '—' : _fmt(item.submittedAt!),
              ),
            ];
            return ListView(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              children: [
                PgCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              item.displayName.isEmpty
                                  ? 'Admission #${item.id}'
                                  : item.displayName,
                              style: Theme.of(context).textTheme.titleLarge,
                            ),
                          ),
                          PgStatusBadge(
                            label: item.statusLabel,
                            tone: item.isSubmitted
                                ? PgStatusTone.approved
                                : PgStatusTone.pending,
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                PgCard(
                  padding: EdgeInsets.zero,
                  child: Column(
                    children: rows
                        .map(
                          (row) => ListTile(
                            title: Text(row.$1),
                            subtitle: Text(row.$2),
                          ),
                        )
                        .toList(),
                  ),
                ),
              ],
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
