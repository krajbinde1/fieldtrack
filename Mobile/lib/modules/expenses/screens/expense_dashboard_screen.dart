import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../api/expense_api.dart';

class ExpenseDashboardScreen extends StatefulWidget {
  const ExpenseDashboardScreen({super.key});

  @override
  State<ExpenseDashboardScreen> createState() => _ExpenseDashboardScreenState();
}

class _ExpenseDashboardScreenState extends State<ExpenseDashboardScreen> {
  late Future<({ExpenseSummary summary, List<ExpenseRecord> claims})> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<({ExpenseSummary summary, List<ExpenseRecord> claims})> _load() async {
    return (await ExpenseApi.create()).mine();
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'My Expenses',
      showBack: true,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const PgLoadingState();
            }
            if (snapshot.hasError) {
              return PgErrorState(message: '${snapshot.error}', onRetry: _refresh);
            }
            final data = snapshot.data!;
            final money = NumberFormat.currency(locale: 'en_IN', symbol: '₹');
            String amount(String value) =>
                money.format(double.tryParse(value) ?? 0);
            return ListView(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              children: [
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _Amount(label: 'Total Submitted', value: amount(data.summary.totalSubmitted)),
                    _Amount(label: 'Pending Approval', value: amount(data.summary.pendingApproval)),
                    _Amount(label: 'Approved', value: amount(data.summary.approved)),
                    _Amount(label: 'Paid', value: amount(data.summary.paid)),
                  ],
                ),
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: () async {
                    await context.push('/expenses/new');
                    if (mounted) await _refresh();
                  },
                  icon: const Icon(Icons.add_rounded),
                  label: const Text('Add Expense'),
                ),
                const SizedBox(height: 16),
                Text('History', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                if (data.claims.isEmpty)
                  const PgEmptyState(message: 'No expenses yet.')
                else
                  ...data.claims.map(
                    (claim) => Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: PgCard(
                        onTap: () async {
                          await context.push('/expenses/${claim.id}');
                          if (mounted) await _refresh();
                        },
                        child: ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(claim.number),
                          subtitle: Text(claim.statusLabel),
                          trailing: Text('₹${claim.amount}'),
                        ),
                      ),
                    ),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _Amount extends StatelessWidget {
  const _Amount({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 160,
      child: PgCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
            const SizedBox(height: 4),
            Text(value, style: const TextStyle(fontWeight: FontWeight.w700)),
          ],
        ),
      ),
    );
  }
}
