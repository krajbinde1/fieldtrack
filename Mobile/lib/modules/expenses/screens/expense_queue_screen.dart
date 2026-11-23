import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../api/expense_api.dart';

class ExpenseQueueScreen extends StatefulWidget {
  const ExpenseQueueScreen({
    super.key,
    required this.apiPrefix,
    required this.detailPrefix,
    this.showMine = false,
    this.centerId,
  });

  final String apiPrefix;
  final String detailPrefix;
  final bool showMine;
  final int? centerId;

  @override
  State<ExpenseQueueScreen> createState() => _ExpenseQueueScreenState();
}

class _ExpenseQueueScreenState extends State<ExpenseQueueScreen> {
  String _status = 'pending';
  Map<String, int> _counts = const {};
  late Future<ExpenseQueueResult> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<ExpenseQueueResult> _load() async {
    final result = await (await ExpenseApi.create()).queue(widget.apiPrefix, status: _status);
    if (mounted) {
      setState(() => _counts = result.counts);
    }
    return result;
  }

  String _chipLabel(String key, String name) {
    final count = _counts[key];
    return count == null ? name : '$name ($count)';
  }

  String _claimLines(ExpenseRecord claim) {
    final pending = claim.pendingWith.isEmpty ? null : 'Pending with ${claim.pendingWith}';
    return [
      claim.statusLabel,
      ?pending,
      _summary(claim),
    ].join('\n');
  }

  String _summary(ExpenseRecord claim) {
    final center = claim.centerName.isEmpty ? claim.number : claim.centerName;
    final item = claim.items.isEmpty ? null : claim.items.first;
    final type = item == null ? '' : ' · ${item['type_label']}';
    final date = item == null ? '' : ' · ${item['expense_date']}';
    return '$center · ₹${claim.amount}$type$date';
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Expenses',
      showBack: true,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (widget.showMine)
                  Align(
                    alignment: Alignment.centerLeft,
                    child: TextButton.icon(
                      onPressed: () {
                        final center = widget.centerId;
                        context.push(center == null ? '/expenses' : '/expenses?center_id=$center');
                      },
                      icon: const Icon(Icons.receipt_long_outlined),
                      label: const Text('My Expenses'),
                    ),
                  ),
                Wrap(
                  spacing: 8,
                  children: [
                    for (final filter in const [
                      ('pending', 'Pending Approval'),
                      ('approved', 'Approved'),
                      ('rejected', 'Rejected'),
                      ('paid', 'Paid'),
                      ('history', 'History'),
                    ])
                      ChoiceChip(
                        label: Text(_chipLabel(filter.$1, filter.$2)),
                        selected: _status == filter.$1,
                        onSelected: (_) => setState(() {
                          _status = filter.$1;
                          _future = _load();
                        }),
                      ),
                  ],
                ),
              ],
            ),
          ),
          Expanded(
            child: FutureBuilder<ExpenseQueueResult>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const PgLoadingState();
                }
                if (snapshot.hasError) {
                  return PgErrorState(message: '${snapshot.error}', onRetry: () => setState(() => _future = _load()));
                }
                final items = snapshot.data?.claims ?? const <ExpenseRecord>[];
                if (items.isEmpty) {
                  return const PgEmptyState(message: 'No expenses in this list.');
                }
                return ListView.builder(
                  padding: const EdgeInsets.all(AppSpacing.screenPadding),
                  itemCount: items.length,
                  itemBuilder: (context, index) {
                    final claim = items[index];
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: PgCard(
                        onTap: () => context.push('${widget.detailPrefix}/${claim.id}'),
                        child: ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(claim.employeeName.isEmpty ? claim.number : claim.employeeName),
                          subtitle: Text(_claimLines(claim)),
                          trailing: Text('₹${claim.amount}'),
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
