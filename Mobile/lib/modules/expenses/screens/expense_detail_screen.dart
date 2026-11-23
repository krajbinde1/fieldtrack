import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../api/expense_api.dart';

class ExpenseDetailScreen extends StatefulWidget {
  const ExpenseDetailScreen({
    super.key,
    required this.claimId,
    this.apiPrefix = '/expenses',
    this.canDecide = false,
    this.canPay = false,
  });

  final int claimId;
  final String apiPrefix;
  final bool canDecide;
  final bool canPay;

  @override
  State<ExpenseDetailScreen> createState() => _ExpenseDetailScreenState();
}

class _ExpenseDetailScreenState extends State<ExpenseDetailScreen> {
  late Future<ExpenseRecord> _future;
  final _remarks = TextEditingController();
  final _reference = TextEditingController();
  String _paymentMode = 'upi';

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _remarks.dispose();
    _reference.dispose();
    super.dispose();
  }

  Future<void> _openReceipt(int itemId) async {
    try {
      final bytes = await (await ExpenseApi.create()).downloadReceipt(widget.apiPrefix, widget.claimId, itemId);
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/expense-receipt-$itemId');
      await file.writeAsBytes(Uint8List.fromList(bytes), flush: true);
      await OpenFilex.open(file.path);
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$error')));
    }
  }

  Future<ExpenseRecord> _load() async {
    return (await ExpenseApi.create()).show(widget.apiPrefix, widget.claimId);
  }

  Future<void> _act(Future<ExpenseRecord> Function(ExpenseApi api) action) async {
    try {
      await action(await ExpenseApi.create());
      if (!mounted) return;
      setState(() => _future = _load());
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$error')));
    }
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Expense',
      showBack: true,
      body: FutureBuilder<ExpenseRecord>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const PgLoadingState();
          }
          if (snapshot.hasError) {
            return PgErrorState(message: '${snapshot.error}', onRetry: () => setState(() => _future = _load()));
          }
          final claim = snapshot.data!;
          final pending = claim.status.startsWith('pending') || claim.status == 'needs_resolution';
          return ListView(
            padding: const EdgeInsets.all(AppSpacing.screenPadding),
            children: [
              Text(claim.number, style: Theme.of(context).textTheme.titleLarge),
              Text(claim.statusLabel),
              Text('Amount ₹${claim.amount}'),
              Text('Payment: ${claim.paymentStatus}'),
              if (claim.rejection.isNotEmpty) Text('Remarks: ${claim.rejection}'),
              const SizedBox(height: 12),
              ...claim.items.map((item) => Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: PgCard(
                      child: ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text('${item['type_label']} · ₹${item['amount']}'),
                        subtitle: Text('${item['expense_date']} · ${item['description']}'
                            '${item['travel_from'] == null || '${item['travel_from']}'.isEmpty ? '' : '\n${item['travel_from']} → ${item['travel_to']}'}'),
                        trailing: item['has_receipt'] == true
                            ? IconButton(
                                tooltip: 'Open receipt',
                                onPressed: () => _openReceipt(int.tryParse('${item['id']}') ?? 0),
                                icon: const Icon(Icons.receipt_long),
                              )
                            : const Icon(Icons.receipt_long_outlined),
                      ),
                    ),
                  )),
              const SizedBox(height: 8),
              Text('Approval history', style: Theme.of(context).textTheme.titleMedium),
              if (claim.approvals.isEmpty) const Text('No decisions yet.'),
              ...claim.approvals.map((row) => Text(
                    'Level ${row['level']} ${row['action']} by ${row['approver_name']}'
                    '${row['remarks'] == null || '${row['remarks']}'.isEmpty ? '' : ' — ${row['remarks']}'}',
                  )),
              if (claim.payment != null) ...[
                const SizedBox(height: 12),
                Text('Paid on ${claim.payment!['paid_on']} via ${claim.payment!['payment_mode']}'),
                if ('${claim.payment!['reference_number'] ?? ''}'.isNotEmpty)
                  Text('Reference ${claim.payment!['reference_number']}'),
              ],
              if (widget.canDecide && pending) ...[
                const SizedBox(height: 12),
                TextField(controller: _remarks, decoration: const InputDecoration(labelText: 'Remarks')),
                const SizedBox(height: 8),
                FilledButton(
                  onPressed: () => _act((api) => api.decide(widget.apiPrefix, claim.id, 'approve', remarks: _remarks.text)),
                  child: const Text('Approve'),
                ),
                const SizedBox(height: 8),
                OutlinedButton(
                  onPressed: () => _act((api) => api.decide(widget.apiPrefix, claim.id, 'reject', remarks: _remarks.text)),
                  child: const Text('Reject'),
                ),
              ],
              if (widget.canPay && claim.status == 'approved' && claim.paymentStatus == 'unpaid') ...[
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _paymentMode,
                  decoration: const InputDecoration(labelText: 'Payment mode'),
                  items: const [
                    DropdownMenuItem(value: 'cash', child: Text('Cash')),
                    DropdownMenuItem(value: 'upi', child: Text('UPI')),
                    DropdownMenuItem(value: 'bank_transfer', child: Text('Bank transfer')),
                    DropdownMenuItem(value: 'cheque', child: Text('Cheque')),
                    DropdownMenuItem(value: 'other', child: Text('Other')),
                  ],
                  onChanged: (value) => setState(() => _paymentMode = value ?? 'upi'),
                ),
                TextField(controller: _reference, decoration: const InputDecoration(labelText: 'Reference number')),
                const SizedBox(height: 8),
                FilledButton(
                  onPressed: () => _act((api) => api.pay(claim.id, {
                        'amount': claim.amount,
                        'payment_mode': _paymentMode,
                        'reference_number': _reference.text,
                        'paid_on': DateTime.now().toIso8601String().substring(0, 10),
                        'remarks': _remarks.text,
                      })),
                  child: const Text('Mark Paid'),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}
