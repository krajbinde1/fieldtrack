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
  late Future<List<ExpenseRecord>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<ExpenseRecord>> _load() async {
    return (await ExpenseApi.create()).queue(widget.apiPrefix, status: _status);
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
                      ('pending', 'Pending'),
                      ('approved', 'Approved'),
                      ('rejected', 'Rejected'),
                      ('paid', 'Paid'),
                      ('history', 'History'),
                    ])
                      ChoiceChip(
                        label: Text(filter.$2),
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
            child: FutureBuilder<List<ExpenseRecord>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const PgLoadingState();
                }
                if (snapshot.hasError) {
                  return PgErrorState(message: '${snapshot.error}', onRetry: () => setState(() => _future = _load()));
                }
                final items = snapshot.data ?? const <ExpenseRecord>[];
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
                          subtitle: Text(_summary(claim)),
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
