import 'dart:async';
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

class _ChosenStop {
  const _ChosenStop({required this.label, required this.manual});

  final String label;
  final bool manual;
}

class _RouteSuggestion {
  _RouteSuggestion({
    required this.key,
    required this.label,
    required this.named,
    this.latitude,
    this.longitude,
    this.recordedAt = '',
  });

  final String key;
  String label;
  bool named;
  final double? latitude;
  final double? longitude;
  final String recordedAt;

  String? get timeLabel {
    if (recordedAt.length < 16) return null;
    return recordedAt.substring(11, 16);
  }
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
  int quoteGeneration = 0;
  bool locationsCustomized = false;
  List<_RouteSuggestion> suggestions = [];
  List<_ChosenStop> fromPlaces = [];
  List<_ChosenStop> toPlaces = [];
}

class _ExpenseFormScreenState extends State<ExpenseFormScreen> {
  final _lines = [_ExpenseLine()];
  final _addressCache = <String, String>{};
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
    setState(() {
      final line = _lines[index];
      line.date.text = '${picked.year}-${two(picked.month)}-${two(picked.day)}';
      line.locationsCustomized = false;
      line.fromPlaces = [];
      line.toPlaces = [];
      line.suggestions = [];
      line.gpsFrom = null;
      line.gpsTo = null;
      line.quote = null;
    });
    await _loadQuote(index);
  }

  Future<String> _address(double latitude, double longitude) async {
    final key = '${latitude.toStringAsFixed(5)},${longitude.toStringAsFixed(5)}';
    final cached = _addressCache[key];
    if (cached != null) return cached;
    try {
      final marks = await placemarkFromCoordinates(latitude, longitude);
      if (marks.isEmpty) return _remember(key, '$latitude, $longitude');
      final mark = marks.first;
      final parts = [mark.name, mark.subLocality, mark.locality]
          .whereType<String>()
          .where((part) => part.trim().isNotEmpty)
          .toSet()
          .join(', ');
      return _remember(key, parts.isEmpty ? '$latitude, $longitude' : parts);
    } catch (_) {
      return _remember(key, '$latitude, $longitude');
    }
  }

  String _remember(String key, String value) {
    _addressCache[key] = value;
    return value;
  }

  bool _quoteIsCurrent(int index, _ExpenseLine line, int generation) {
    return mounted &&
        index < _lines.length &&
        identical(_lines[index], line) &&
        line.quoteGeneration == generation;
  }

  List<_RouteSuggestion> _parseSuggestions(dynamic raw) {
    if (raw is! List) return [];
    final suggestions = <_RouteSuggestion>[];
    for (var i = 0; i < raw.length; i++) {
      final item = raw[i];
      if (item is! Map) continue;
      final map = Map<String, dynamic>.from(item);
      final address = '${map['address'] ?? ''}'.trim();
      final fallback = '${map['label'] ?? ''}'.trim();
      final latitude = double.tryParse('${map['latitude']}');
      final longitude = double.tryParse('${map['longitude']}');
      final label = address.isNotEmpty
          ? address
          : (fallback.isNotEmpty
              ? fallback
              : (latitude != null && longitude != null ? '$latitude, $longitude' : ''));
      if (label.isEmpty) continue;
      suggestions.add(
        _RouteSuggestion(
          key: '${map['id'] ?? 'stop'}|$i|${map['recorded_at'] ?? ''}',
          label: label,
          named: address.isNotEmpty,
          latitude: latitude,
          longitude: longitude,
          recordedAt: '${map['recorded_at'] ?? ''}',
        ),
      );
    }
    return suggestions;
  }

  List<_ChosenStop> _withUpdatedLabel(List<_ChosenStop> stops, String from, String to) {
    if (from == to) return stops;
    return [
      for (final stop in stops)
        !stop.manual && stop.label == from ? _ChosenStop(label: to, manual: false) : stop,
    ];
  }

  void _applyDefaultStops(_ExpenseLine line) {
    if (line.locationsCustomized) return;
    if (line.suggestions.isEmpty) {
      line.fromPlaces = [];
      line.toPlaces = [];
      return;
    }
    line.fromPlaces = [_ChosenStop(label: line.suggestions.first.label, manual: false)];
    line.toPlaces = [_ChosenStop(label: line.suggestions.last.label, manual: false)];
  }

  String _coordinateLabel(Map<String, dynamic>? point) {
    if (point == null) return '';
    final latitude = double.tryParse('${point['latitude']}');
    final longitude = double.tryParse('${point['longitude']}');
    if (latitude == null || longitude == null) return '';
    return '$latitude, $longitude';
  }

  Future<void> _loadQuote(int index) async {
    if (index < 0 || index >= _lines.length) return;
    final line = _lines[index];
    if (line.type != 'travelling' || line.travelMode != 'self' || line.date.text.length != 10) {
      return;
    }
    final generation = ++line.quoteGeneration;
    setState(() {
      line.loadingQuote = true;
      line.quoteError = null;
      line.quote = null;
      line.suggestions = [];
      line.gpsFrom = null;
      line.gpsTo = null;
      if (!line.locationsCustomized) {
        line.fromPlaces = [];
        line.toPlaces = [];
      }
    });
    try {
      final quote = await (await ExpenseApi.create()).selfTravel(line.date.text);
      if (!_quoteIsCurrent(index, line, generation)) return;
      final from = quote['from'] is Map ? Map<String, dynamic>.from(quote['from'] as Map) : null;
      final to = quote['to'] is Map ? Map<String, dynamic>.from(quote['to'] as Map) : null;
      setState(() {
        line.quote = quote;
        line.quoteError = quote['available'] == true ? null : '${quote['message'] ?? 'GPS distance is not available.'}';
        line.suggestions = _parseSuggestions(quote['locations']);
        if (quote['available'] == true) {
          line.amount.text = '${quote['amount']}';
          final start = _coordinateLabel(from);
          final end = _coordinateLabel(to);
          if (start.isNotEmpty) line.gpsFrom = start;
          if (end.isNotEmpty) line.gpsTo = end;
        }
        _applyDefaultStops(line);
      });
      unawaited(_resolveLabels(index, generation, from, to));
    } catch (error) {
      if (_quoteIsCurrent(index, line, generation)) {
        line.quoteError = '$error';
      }
    } finally {
      if (mounted && line.quoteGeneration == generation) {
        setState(() => line.loadingQuote = false);
      }
    }
  }

  Future<void> _resolveLabels(
    int index,
    int generation,
    Map<String, dynamic>? from,
    Map<String, dynamic>? to,
  ) async {
    if (index < 0 || index >= _lines.length) return;
    final line = _lines[index];
    if (!_quoteIsCurrent(index, line, generation)) return;
    Future<void> nameEndpoint(Map<String, dynamic>? point, void Function(String label) apply) async {
      if (point == null) return;
      final latitude = double.tryParse('${point['latitude']}');
      final longitude = double.tryParse('${point['longitude']}');
      if (latitude == null || longitude == null) return;
      final label = await _address(latitude, longitude);
      if (!_quoteIsCurrent(index, line, generation)) return;
      setState(() => apply(label));
    }

    await nameEndpoint(from, (label) => line.gpsFrom = label);
    if (!_quoteIsCurrent(index, line, generation)) return;
    await nameEndpoint(to, (label) => line.gpsTo = label);
    if (!_quoteIsCurrent(index, line, generation)) return;

    for (final suggestion in List<_RouteSuggestion>.from(line.suggestions)) {
      if (suggestion.named || suggestion.latitude == null || suggestion.longitude == null) continue;
      final label = await _address(suggestion.latitude!, suggestion.longitude!);
      if (!_quoteIsCurrent(index, line, generation)) return;
      if (!line.suggestions.any((item) => item.key == suggestion.key)) return;
      setState(() {
        final previous = suggestion.label;
        suggestion.label = label;
        suggestion.named = true;
        if (!line.locationsCustomized) {
          _applyDefaultStops(line);
        } else {
          line.fromPlaces = _withUpdatedLabel(line.fromPlaces, previous, label);
          line.toPlaces = _withUpdatedLabel(line.toPlaces, previous, label);
        }
      });
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

  String _joinStops(List<_ChosenStop> stops) {
    return stops.map((stop) => stop.label.trim()).where((label) => label.isNotEmpty).join(' | ');
  }

  String _stopSource(List<_ChosenStop> stops) {
    return stops.any((stop) => stop.manual) ? 'manual' : 'gps';
  }

  bool _showsQuoteFigures(_ExpenseLine line) {
    final quote = line.quote;
    if (quote == null) return false;
    if (quote['available'] == true) return true;
    const keys = ['recorded_km', 'km_rate', 'monthly_km_used', 'monthly_km_limit', 'remaining_km', 'amount'];
    return keys.any((key) => quote[key] != null && '${quote[key]}'.trim().isNotEmpty && '${quote[key]}' != 'null');
  }

  String _figure(Map<String, dynamic> quote, String key, {String prefix = ''}) {
    final raw = quote[key];
    if (raw == null || '$raw'.trim().isEmpty || '$raw' == 'null') return '—';
    return '$prefix$raw';
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
      final items = _lines.map((line) {
        final self = line.type == 'travelling' && line.travelMode == 'self';
        return {
          'expense_date': line.date.text.trim(),
          'type': line.type,
          if (line.type == 'travelling') 'travel_mode': line.travelMode,
          'amount': line.amount.text.trim(),
          'description': line.description.text.trim(),
          'travel_from': self ? _joinStops(line.fromPlaces) : line.from.text.trim(),
          'travel_to': self ? _joinStops(line.toPlaces) : line.to.text.trim(),
          'travel_from_source': self ? _stopSource(line.fromPlaces) : (line.manualFrom ? 'manual' : 'gps'),
          'travel_to_source': self ? _stopSource(line.toPlaces) : (line.manualTo ? 'manual' : 'gps'),
          if (line.gpsFrom != null) 'gps_from_address': line.gpsFrom,
          if (line.gpsTo != null) 'gps_to_address': line.gpsTo,
          if (line.receiptToken != null) 'receipt_token': line.receiptToken,
        };
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

  Widget _field(Widget child) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: child,
    );
  }

  InputDecoration _decoration(String label) {
    return InputDecoration(
      labelText: label,
      floatingLabelBehavior: FloatingLabelBehavior.auto,
      contentPadding: const EdgeInsets.fromLTRB(16, 20, 16, 16),
    );
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
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text('Expense ${index + 1}', style: Theme.of(context).textTheme.titleSmall),
            ),
            _field(
              DropdownButtonFormField<String>(
                initialValue: _lines[index].type,
                isExpanded: true,
                decoration: _decoration('Type'),
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
            ),
            if (_lines[index].type == 'travelling')
              _field(
                DropdownButtonFormField<String>(
                  initialValue: _lines[index].travelMode,
                  isExpanded: true,
                  decoration: _decoration('Travelling type'),
                  items: const [
                    DropdownMenuItem(value: 'self', child: Text('Self Travelling')),
                    DropdownMenuItem(value: 'public', child: Text('Public Transport')),
                  ],
                  onChanged: (value) {
                    setState(() => _lines[index].travelMode = value ?? 'self');
                    _loadQuote(index);
                  },
                ),
              ),
            _field(
              TextField(
                controller: _lines[index].date,
                readOnly: true,
                decoration: _decoration('Expense Date'),
                onTap: () => _pickDate(index),
              ),
            ),
            if (_lines[index].type == 'travelling' && _lines[index].travelMode == 'self') ...[
              if (_lines[index].loadingQuote)
                const Padding(
                  padding: EdgeInsets.only(bottom: 16),
                  child: LinearProgressIndicator(),
                ),
              if (_lines[index].quoteError != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text(
                    _lines[index].quoteError!,
                    style: TextStyle(color: Theme.of(context).colorScheme.error),
                  ),
                ),
              if (_lines[index].quote?['warning'] != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text('${_lines[index].quote!['warning']}'),
                ),
              if (_showsQuoteFigures(_lines[index])) ...[
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text(
                    'Taken from this date\'s GPS route. These values cannot be edited.',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
                _ReadOnlyField(label: 'Recorded KM', value: _figure(_lines[index].quote!, 'recorded_km')),
                _ReadOnlyField(label: 'KM Rate', value: _figure(_lines[index].quote!, 'km_rate', prefix: '₹')),
                _ReadOnlyField(label: 'Monthly KM Used', value: _figure(_lines[index].quote!, 'monthly_km_used')),
                _ReadOnlyField(label: 'Monthly KM Limit', value: _figure(_lines[index].quote!, 'monthly_km_limit')),
                _ReadOnlyField(label: 'Remaining KM', value: _figure(_lines[index].quote!, 'remaining_km')),
                _ReadOnlyField(
                  label: 'Calculated Amount',
                  value: _figure(_lines[index].quote!, 'amount', prefix: '₹'),
                ),
              ],
              _LocationEditor(
                key: ValueKey('from-$index'),
                label: 'Travel From',
                places: _lines[index].fromPlaces,
                suggestions: _lines[index].suggestions,
                onChanged: (stops) {
                  setState(() {
                    _lines[index].locationsCustomized = true;
                    _lines[index].fromPlaces = stops;
                  });
                },
              ),
              const SizedBox(height: 16),
              _LocationEditor(
                key: ValueKey('to-$index'),
                label: 'Travel To',
                places: _lines[index].toPlaces,
                suggestions: _lines[index].suggestions,
                onChanged: (stops) {
                  setState(() {
                    _lines[index].locationsCustomized = true;
                    _lines[index].toPlaces = stops;
                  });
                },
              ),
              const SizedBox(height: 16),
            ],
            if (!(_lines[index].type == 'travelling' && _lines[index].travelMode == 'self'))
              _field(
                TextField(
                  controller: _lines[index].amount,
                  keyboardType: TextInputType.number,
                  decoration: _decoration('Amount'),
                ),
              ),
            _field(
              TextField(
                controller: _lines[index].description,
                decoration: _decoration('Description'),
              ),
            ),
            if (_lines[index].type == 'travelling' && _lines[index].travelMode != 'self') ...[
              _field(
                TextField(
                  controller: _lines[index].from,
                  decoration: _decoration('Travel from'),
                ),
              ),
              _field(
                TextField(
                  controller: _lines[index].to,
                  decoration: _decoration('Travel to'),
                ),
              ),
            ],
            _ReceiptPicker(
              line: _lines[index],
              onPick: () => _pickReceipt(index),
              onRemove: () => _removeReceipt(index),
            ),
            const Divider(height: 32),
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

class _ReadOnlyField extends StatelessWidget {
  const _ReadOnlyField({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Semantics(
        readOnly: true,
        label: label,
        value: value,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(label, style: Theme.of(context).textTheme.labelLarge),
            const SizedBox(height: 6),
            DecoratedBox(
              decoration: BoxDecoration(
                color: scheme.surface,
                borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
                border: Border.all(color: scheme.outline),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
                child: Text(value, style: Theme.of(context).textTheme.bodyLarge),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LocationEditor extends StatefulWidget {
  const _LocationEditor({
    super.key,
    required this.label,
    required this.places,
    required this.suggestions,
    required this.onChanged,
  });

  final String label;
  final List<_ChosenStop> places;
  final List<_RouteSuggestion> suggestions;
  final ValueChanged<List<_ChosenStop>> onChanged;

  @override
  State<_LocationEditor> createState() => _LocationEditorState();
}

class _LocationEditorState extends State<_LocationEditor> {
  final _query = TextEditingController();
  final _focus = FocusNode();

  @override
  void initState() {
    super.initState();
    _focus.addListener(_onFocus);
  }

  void _onFocus() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _focus.removeListener(_onFocus);
    _query.dispose();
    _focus.dispose();
    super.dispose();
  }

  List<_RouteSuggestion> _matches() {
    final query = _query.text.trim().toLowerCase();
    if (query.isEmpty) return widget.suggestions;
    return widget.suggestions.where((item) => item.label.toLowerCase().contains(query)).toList();
  }

  void _addSuggestion(_RouteSuggestion suggestion) {
    widget.onChanged([
      ...widget.places,
      _ChosenStop(label: suggestion.label, manual: false),
    ]);
    _query.clear();
    _focus.unfocus();
  }

  void _addManual() {
    final text = _query.text.trim();
    if (text.isEmpty) return;
    widget.onChanged([
      ...widget.places,
      _ChosenStop(label: text, manual: true),
    ]);
    _query.clear();
    _focus.unfocus();
  }

  void _move(int from, int to) {
    final next = [...widget.places];
    final item = next.removeAt(from);
    next.insert(to, item);
    widget.onChanged(next);
  }

  void _remove(int index) {
    final next = [...widget.places]..removeAt(index);
    widget.onChanged(next);
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final matches = _matches();
    final showSuggestions = _focus.hasFocus || _query.text.trim().isNotEmpty;
    final typed = _query.text.trim();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(widget.label, style: Theme.of(context).textTheme.titleSmall),
        const SizedBox(height: 4),
        Text(
          'Add stops in travel order. Search this date\'s route, or type a location.',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 8),
        if (widget.places.isEmpty)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Text('No locations yet.', style: Theme.of(context).textTheme.bodyMedium),
          ),
        for (var index = 0; index < widget.places.length; index++)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: scheme.surface,
                borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
                border: Border.all(color: scheme.outline),
              ),
              child: Padding(
                padding: const EdgeInsets.only(left: 12),
                child: Row(
                  children: [
                    SizedBox(
                      width: 28,
                      child: Text('${index + 1}.', style: Theme.of(context).textTheme.titleSmall),
                    ),
                    Expanded(
                      child: Text(
                        widget.places[index].label,
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    IconButton(
                      tooltip: 'Move earlier',
                      visualDensity: VisualDensity.compact,
                      onPressed: index == 0 ? null : () => _move(index, index - 1),
                      icon: const Icon(Icons.keyboard_arrow_up),
                    ),
                    IconButton(
                      tooltip: 'Move later',
                      visualDensity: VisualDensity.compact,
                      onPressed: index == widget.places.length - 1 ? null : () => _move(index, index + 1),
                      icon: const Icon(Icons.keyboard_arrow_down),
                    ),
                    IconButton(
                      tooltip: 'Remove',
                      visualDensity: VisualDensity.compact,
                      onPressed: () => _remove(index),
                      icon: const Icon(Icons.close),
                    ),
                  ],
                ),
              ),
            ),
          ),
        TextField(
          controller: _query,
          focusNode: _focus,
          textInputAction: TextInputAction.done,
          decoration: const InputDecoration(
            labelText: 'Search or type a location',
            contentPadding: EdgeInsets.fromLTRB(16, 20, 16, 16),
          ),
          onChanged: (_) => setState(() {}),
          onSubmitted: (_) => _addManual(),
        ),
        if (showSuggestions) ...[
          const SizedBox(height: 8),
          DecoratedBox(
            decoration: BoxDecoration(
              color: scheme.surface,
              borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
              border: Border.all(color: scheme.outline),
            ),
            child: matches.isEmpty
                ? Padding(
                    padding: const EdgeInsets.all(12),
                    child: Text(
                      widget.suggestions.isEmpty
                          ? 'No GPS stops for this date. Type a location and add it.'
                          : 'No matching stops on this date.',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  )
                : ConstrainedBox(
                    constraints: const BoxConstraints(maxHeight: 180),
                    child: ListView.builder(
                      shrinkWrap: true,
                      primary: false,
                      padding: EdgeInsets.zero,
                      itemCount: matches.length,
                      itemBuilder: (context, index) {
                        final suggestion = matches[index];
                        final time = suggestion.timeLabel;
                        return ListTile(
                          dense: true,
                          title: Text(suggestion.label, maxLines: 2, overflow: TextOverflow.ellipsis),
                          subtitle: time == null ? null : Text(time),
                          onTap: () => _addSuggestion(suggestion),
                        );
                      },
                    ),
                  ),
          ),
        ],
        const SizedBox(height: 8),
        Align(
          alignment: Alignment.centerLeft,
          child: OutlinedButton(
            style: OutlinedButton.styleFrom(
              visualDensity: VisualDensity.compact,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            ),
            onPressed: typed.isEmpty ? null : _addManual,
            child: const Text('Add typed location'),
          ),
        ),
      ],
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
