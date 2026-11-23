import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../api/expense_api.dart';

class ExpenseFormScreen extends StatefulWidget {
  const ExpenseFormScreen({super.key, this.centerId});

  final int? centerId;

  @override
  State<ExpenseFormScreen> createState() => _ExpenseFormScreenState();
}

class _ExpenseLine {
  String type = 'travelling';
  final date = TextEditingController();
  final amount = TextEditingController();
  final description = TextEditingController();
  final from = TextEditingController();
  final to = TextEditingController();
  final km = TextEditingController();
  String? receipt;
}

class _ExpenseFormScreenState extends State<ExpenseFormScreen> {
  final _lines = [_ExpenseLine()];
  bool _saving = false;

  @override
  void dispose() {
    for (final line in _lines) {
      line.date.dispose();
      line.amount.dispose();
      line.description.dispose();
      line.from.dispose();
      line.to.dispose();
      line.km.dispose();
    }
    super.dispose();
  }

  Future<void> _save(String action) async {
    setState(() => _saving = true);
    try {
      final items = _lines.map((line) => {
            'expense_date': line.date.text.trim(),
            'type': line.type,
            'amount': line.amount.text.trim(),
            'description': line.description.text.trim(),
            'travel_from': line.from.text.trim(),
            'travel_to': line.to.text.trim(),
            'travel_km': line.km.text.trim(),
          }).toList();
      await (await ExpenseApi.create()).save(
        action: action,
        items: items,
        receipts: _lines.map((line) => line.receipt).toList(),
        centerId: widget.centerId,
      );
      if (mounted) context.pop();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$error')));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Add Expense',
      showBack: true,
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.screenPadding),
        children: [
          for (var index = 0; index < _lines.length; index++) ...[
            Text('Expense ${index + 1}', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            DropdownButtonFormField<String>(
              initialValue: _lines[index].type,
              decoration: const InputDecoration(labelText: 'Type'),
              items: const [
                DropdownMenuItem(value: 'travelling', child: Text('Travelling')),
                DropdownMenuItem(value: 'hotel', child: Text('Hotel')),
                DropdownMenuItem(value: 'food', child: Text('Food')),
                DropdownMenuItem(value: 'other', child: Text('Other')),
              ],
              onChanged: (value) => setState(() => _lines[index].type = value ?? 'other'),
            ),
            TextField(controller: _lines[index].date, decoration: const InputDecoration(labelText: 'Expense Date (YYYY-MM-DD)')),
            TextField(controller: _lines[index].amount, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Amount')),
            TextField(controller: _lines[index].description, decoration: const InputDecoration(labelText: 'Description')),
            TextField(controller: _lines[index].from, decoration: const InputDecoration(labelText: 'Travel from (optional)')),
            TextField(controller: _lines[index].to, decoration: const InputDecoration(labelText: 'Travel to (optional)')),
            TextField(controller: _lines[index].km, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Travel km (optional)')),
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton.icon(
                onPressed: () async {
                  final file = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 70);
                  if (file != null) setState(() => _lines[index].receipt = file.path);
                },
                icon: const Icon(Icons.receipt_long_outlined),
                label: Text(_lines[index].receipt == null ? 'Add receipt' : 'Receipt added'),
              ),
            ),
            const Divider(),
          ],
          TextButton.icon(
            onPressed: () => setState(() => _lines.add(_ExpenseLine())),
            icon: const Icon(Icons.add),
            label: const Text('Add another expense'),
          ),
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: _saving ? null : () => _save('draft'),
            child: const Text('Save Draft'),
          ),
          const SizedBox(height: 8),
          FilledButton(
            onPressed: _saving ? null : () => _save('submit'),
            child: const Text('Submit'),
          ),
        ],
      ),
    );
  }
}
