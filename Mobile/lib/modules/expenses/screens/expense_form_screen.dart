import 'dart:io';

import 'package:flutter/material.dart';
import 'package:geocoding/geocoding.dart';
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
  String travelMode = 'self';
  final date = TextEditingController();
  final amount = TextEditingController();
  final description = TextEditingController();
  final from = TextEditingController();
  final to = TextEditingController();
  bool manualFrom = false;
  bool manualTo = false;
  String? gpsFrom;
  String? gpsTo;
  String? receiptPath;
  String? receiptToken;
  String? receiptName;
  String receiptStatus = 'none';
  Map<String, dynamic>? quote;
  String? quoteError;
  bool loadingQuote = false;
}

class _ExpenseFormScreenState extends State<ExpenseFormScreen> {
  final _lines = [_ExpenseLine()];
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _lines.first.date.text = _today();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadQuote(0));
  }

  @override
  void dispose() {
    for (final line in _lines) {
      line.date.dispose();
      line.amount.dispose();
      line.description.dispose();
      line.from.dispose();
      line.to.dispose();
    }
    super.dispose();
  }

  String _today() {
    final now = DateTime.now();
    String two(int value) => value.toString().padLeft(2, '0');
    return '${now.year}-${two(now.month)}-${two(now.day)}';
  }

  Future<void> _pickDate(int index) async {
    final current = DateTime.tryParse(_lines[index].date.text) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: current,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
    );
    if (picked == null) return;
    String two(int value) => value.toString().padLeft(2, '0');
    setState(() => _lines[index].date.text = '${picked.year}-${two(picked.month)}-${two(picked.day)}');
    await _loadQuote(index);
  }

  Future<String> _address(double latitude, double longitude) async {
    try {
      final marks = await placemarkFromCoordinates(latitude, longitude);
      if (marks.isEmpty) return '$latitude, $longitude';
      final mark = marks.first;
      final parts = [mark.name, mark.subLocality, mark.locality]
          .whereType<String>()
          .where((part) => part.trim().isNotEmpty)
          .toSet()
          .join(', ');
      return parts.isEmpty ? '$latitude, $longitude' : parts;
    } catch (_) {
      return '$latitude, $longitude';
    }
  }

  Future<void> _loadQuote(int index) async {
    final line = _lines[index];
    if (line.type != 'travelling' || line.travelMode != 'self' || line.date.text.length != 10) {
      return;
    }
    setState(() {
      line.loadingQuote = true;
      line.quoteError = null;
    });
    try {
      final quote = await (await ExpenseApi.create()).selfTravel(line.date.text);
      if (!mounted) return;
      line.quote = quote;
      line.quoteError = quote['available'] == true ? null : '${quote['message'] ?? 'GPS distance is not available.'}';
            if (quote['available'] == true) {
        line.amount.text = '${quote['amount']}';
        final from = quote['from'] is Map ? Map<String, dynamic>.from(quote['from'] as Map) : null;
        final to = quote['to'] is Map ? Map<String, dynamic>.from(quote['to'] as Map) : null;
        if (from != null) {
          line.gpsFrom = await _address(
            double.tryParse('${from['latitude']}') ?? 0,
            double.tryParse('${from['longitude']}') ?? 0,
          );
          if (!line.manualFrom) line.from.text = line.gpsFrom!;
        }
        if (to != null) {
          line.gpsTo = await _address(
            double.tryParse('${to['latitude']}') ?? 0,
            double.tryParse('${to['longitude']}') ?? 0,
          );
          if (!line.manualTo) line.to.text = line.gpsTo!;
        }
      }
    } catch (error) {
      line.quoteError = '$error';
    } finally {
      if (mounted) setState(() => line.loadingQuote = false);
    }
  }

  Future<void> _pickReceipt(int index) async {
    final file = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 70);
    if (file == null) return;
    final line = _lines[index];
    setState(() {
      line.receiptPath = file.path;
      line.receiptName = file.name;
      line.receiptToken = null;
      line.receiptStatus = 'uploading';
    });
    try {
      final uploaded = await (await ExpenseApi.create()).uploadReceipt(file.path);
      if (!mounted) return;
      setState(() {
        line.receiptToken = uploaded.token;
        line.receiptName = uploaded.filename.isEmpty ? file.name : uploaded.filename;
        line.receiptStatus = 'uploaded';
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => line.receiptStatus = 'failed');
    }
  }

  void _removeReceipt(int index) {
    setState(() {
      _lines[index].receiptPath = null;
      _lines[index].receiptToken = null;
      _lines[index].receiptName = null;
      _lines[index].receiptStatus = 'none';
    });
  }

  Future<void> _save(String action) async {
    final seen = <String>{};
    for (final line in _lines) {
      if (line.type == 'travelling' && line.travelMode == 'self') {
        if (!seen.add(line.date.text)) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Self Travelling is already included for ${line.date.text}.')),
          );
          return;
        }
        if (line.quoteError != null || line.quote?['available'] != true) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(line.quoteError ?? 'Self Travelling is not ready for this date.')),
          );
          return;
        }
      }
      if (line.receiptStatus == 'uploading') {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Wait for the receipt upload to finish.')));
        return;
      }
      if (line.receiptStatus == 'failed') {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Replace the receipt that failed to upload.')));
        return;
      }
    }

    setState(() => _saving = true);
    try {
      final items = _lines.map((line) => {
            'expense_date': line.date.text.trim(),
            'type': line.type,
            if (line.type == 'travelling') 'travel_mode': line.travelMode,
            'amount': line.amount.text.trim(),
            'description': line.description.text.trim(),
            'travel_from': line.from.text.trim(),
            'travel_to': line.to.text.trim(),
            'travel_from_source': line.manualFrom ? 'manual' : 'gps',
            'travel_to_source': line.manualTo ? 'manual' : 'gps',
            if (line.gpsFrom != null) 'gps_from_address': line.gpsFrom,
            if (line.gpsTo != null) 'gps_to_address': line.gpsTo,
            if (line.receiptToken != null) 'receipt_token': line.receiptToken,
          }).toList();
      await (await ExpenseApi.create()).save(
        action: action,
        items: items,
        receipts: List<String?>.filled(_lines.length, null),
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
              onChanged: (value) {
                setState(() => _lines[index].type = value ?? 'other');
                _loadQuote(index);
              },
            ),
            if (_lines[index].type == 'travelling')
              DropdownButtonFormField<String>(
                initialValue: _lines[index].travelMode,
                decoration: const InputDecoration(labelText: 'Travelling type'),
                items: const [
                  DropdownMenuItem(value: 'self', child: Text('Self Travelling')),
                  DropdownMenuItem(value: 'public', child: Text('Public Transport')),
                ],
                onChanged: (value) {
                  setState(() => _lines[index].travelMode = value ?? 'self');
                  _loadQuote(index);
                },
              ),
            TextField(
              controller: _lines[index].date,
              readOnly: true,
              decoration: const InputDecoration(labelText: 'Expense Date'),
              onTap: () => _pickDate(index),
            ),
            if (_lines[index].type == 'travelling' && _lines[index].travelMode == 'self') ...[
              if (_lines[index].loadingQuote) const LinearProgressIndicator(),
              if (_lines[index].quoteError != null)
                Text(_lines[index].quoteError!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              if (_lines[index].quote?['warning'] != null) Text('${_lines[index].quote!['warning']}'),
              if (_lines[index].quote?['available'] == true) ...[
                Text('Recorded KM ${_lines[index].quote!['recorded_km']}'),
                Text('KM Rate ₹${_lines[index].quote!['km_rate']}'),
                Text('Monthly KM Used ${_lines[index].quote!['monthly_km_used']}'),
                Text('Monthly KM Limit ${_lines[index].quote!['monthly_km_limit']}'),
                Text('Remaining KM ${_lines[index].quote!['remaining_km']}'),
                Text('Calculated Amount ₹${_lines[index].quote!['amount']}'),
              ],
            ],
            if (!(_lines[index].type == 'travelling' && _lines[index].travelMode == 'self'))
              TextField(
                controller: _lines[index].amount,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Amount'),
              ),
            TextField(controller: _lines[index].description, decoration: const InputDecoration(labelText: 'Description')),
            if (_lines[index].type == 'travelling') ...[
              TextField(
                controller: _lines[index].from,
                readOnly: _lines[index].travelMode == 'self' && !_lines[index].manualFrom,
                decoration: const InputDecoration(labelText: 'Travel from'),
              ),
              TextField(
                controller: _lines[index].to,
                readOnly: _lines[index].travelMode == 'self' && !_lines[index].manualTo,
                decoration: const InputDecoration(labelText: 'Travel to'),
              ),
              if (_lines[index].travelMode == 'self')
                Wrap(
                  spacing: 8,
                  children: [
                    TextButton(onPressed: () {
                      setState(() {
                        _lines[index].manualFrom = false;
                        _lines[index].manualTo = false;
                      });
                      _loadQuote(index);
                    }, child: const Text('Use GPS Location')),
                    TextButton(onPressed: () => setState(() {
                      _lines[index].manualFrom = true;
                      _lines[index].manualTo = true;
                    }), child: const Text('Manual Entry')),
                  ],
                ),
            ],
            _ReceiptPicker(
              line: _lines[index],
              onPick: () => _pickReceipt(index),
              onRemove: () => _removeReceipt(index),
            ),
            const Divider(),
          ],
          TextButton.icon(
            onPressed: () {
              final line = _ExpenseLine()..date.text = _today();
              setState(() => _lines.add(line));
              _loadQuote(_lines.length - 1);
            },
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

class _ReceiptPicker extends StatelessWidget {
  const _ReceiptPicker({required this.line, required this.onPick, required this.onRemove});

  final _ExpenseLine line;
  final VoidCallback onPick;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final status = switch (line.receiptStatus) {
      'uploading' => 'Uploading',
      'uploaded' => 'Uploaded Successfully',
      'failed' => 'Upload Failed',
      _ => 'No receipt selected',
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (line.receiptPath != null) ...[
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: Image.file(File(line.receiptPath!), height: 72, width: 72, fit: BoxFit.cover),
          ),
          Text(line.receiptName ?? 'Receipt'),
        ],
        Text(status),
        Wrap(
          spacing: 8,
          children: [
            TextButton(onPressed: onPick, child: Text(line.receiptPath == null ? 'Add receipt' : 'Replace')),
            if (line.receiptPath != null) TextButton(onPressed: onRemove, child: const Text('Remove')),
          ],
        ),
      ],
    );
  }
}
