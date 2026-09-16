import 'dart:io';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../api/admission_api.dart';
import '../models/admission.dart';
import '../widgets/admission_step_indicator.dart';

const _steps = [
  'Scheme',
  'Personal Details',
  'Address',
  'Documents',
  'Review & Submit',
];

const _documentSlots = [
  ('aadhaar', 'Aadhaar Card', true),
  ('marksheet', 'Marksheet', false),
  ('bank_passbook', 'Bank Passbook', false),
  ('caste_certificate', 'Caste Certificate', false),
  ('other', 'Other Document', false),
];

class AdmissionWizardScreen extends StatefulWidget {
  const AdmissionWizardScreen({super.key, this.admissionId});

  final int? admissionId;

  @override
  State<AdmissionWizardScreen> createState() => _AdmissionWizardScreenState();
}

class _AdmissionWizardScreenState extends State<AdmissionWizardScreen> {
  final _firstName = TextEditingController();
  final _middleName = TextEditingController();
  final _lastName = TextEditingController();
  final _village = TextEditingController();

  AdmissionApi? _api;
  bool _loading = true;
  bool _busy = false;
  bool _submittedLock = false;
  String? _error;
  String? _uploadType;
  int _step = 0;
  int? _admissionId;
  bool _editable = true;
  AdmissionRecord? _record;

  List<NamedLookup> _schemes = const [];
  AdmissionLookups _lookups = const AdmissionLookups();
  List<NamedLookup> _talukas = const [];

  int? _schemeId;
  String? _gender;
  String? _religion;
  String? _caste;
  int? _districtId;
  int? _talukaId;
  final Map<String, double> _uploadProgress = {};

  @override
  void initState() {
    super.initState();
    _admissionId = widget.admissionId;
    _bootstrap();
  }

  @override
  void dispose() {
    _firstName.dispose();
    _middleName.dispose();
    _lastName.dispose();
    _village.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      _api ??= await AdmissionApi.create();
      final schemes = await _api!.schemes();
      final lookups = await _api!.lookups();
      AdmissionRecord? record;
      if (_admissionId != null) {
        record = await _api!.show(_admissionId!);
      }
      List<NamedLookup> talukas = const [];
      final districtId = record?.districtId;
      if (districtId != null) {
        talukas = await _api!.talukas(districtId);
      }
      if (!mounted) return;
      setState(() {
        _schemes = schemes;
        _lookups = lookups;
        _talukas = talukas;
        if (record != null) _applyRecord(record);
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = '$error';
        _loading = false;
      });
    }
  }

  void _applyRecord(AdmissionRecord record) {
    _record = record;
    _admissionId = record.id;
    _editable = record.editable;
    _step = record.isSubmitted
        ? 4
        : (record.currentStep - 1).clamp(0, 4);
    _schemeId = record.schemeId;
    _firstName.text = record.firstName ?? '';
    _middleName.text = record.middleName ?? '';
    _lastName.text = record.lastName ?? '';
    _gender = record.gender;
    _religion = record.religion;
    _caste = record.caste;
    _districtId = record.districtId;
    _talukaId = record.talukaId;
    _village.text = record.village ?? '';
    _submittedLock = record.isSubmitted;
  }

  Map<String, dynamic> _payload({required int step}) => {
    'scheme_id': _schemeId,
    'first_name': _firstName.text.trim(),
    'middle_name': _middleName.text.trim(),
    'last_name': _lastName.text.trim(),
    'gender': _gender,
    'religion': _religion,
    'caste': _caste,
    'district_id': _districtId,
    'taluka_id': _talukaId,
    'village': _village.text.trim(),
    'current_step': step,
  };

  String? _validateStep(int step) {
    switch (step) {
      case 0:
        if (_schemeId == null) return 'Please select a scheme.';
      case 1:
        if (_firstName.text.trim().isEmpty) return 'First name is required.';
        if (_lastName.text.trim().isEmpty) return 'Last name is required.';
        if (_gender == null) return 'Please select gender.';
        if (_religion == null) return 'Please select religion.';
        if (_caste == null) return 'Please select caste.';
      case 2:
        if (_districtId == null) return 'Please select a district.';
        if (_talukaId == null) return 'Please select a taluka.';
        if (_village.text.trim().isEmpty) return 'Village is required.';
      case 3:
        if (_record?.documentOf('aadhaar') == null) {
          return 'Please upload the Aadhaar Card.';
        }
    }
    return null;
  }

  Future<bool> _persist({required int step, required bool validate}) async {
    if (!_editable) return false;
    if (validate) {
      final message = _validateStep(_step);
      if (message != null) {
        _toast(message);
        return false;
      }
    }
    setState(() => _busy = true);
    try {
      _api ??= await AdmissionApi.create();
      final record = await _api!.saveDraft(
        _payload(step: step),
        id: _admissionId,
      );
      if (!mounted) return false;
      setState(() {
        _applyRecord(record);
        _busy = false;
      });
      return true;
    } catch (error) {
      if (!mounted) return false;
      setState(() => _busy = false);
      _toast('$error');
      return false;
    }
  }

  Future<void> _saveDraft() async {
    final ok = await _persist(step: _step + 1, validate: false);
    if (ok && mounted) {
      _toast('Draft saved.');
    }
  }

  Future<void> _next() async {
    if (_step < 4) {
      final ok = await _persist(step: _step + 2, validate: true);
      if (ok && mounted) setState(() => _step += 1);
      return;
    }
    await _submit();
  }

  Future<void> _submit() async {
    if (_submittedLock || !_editable) return;
    for (var i = 0; i <= 3; i++) {
      final message = _validateStep(i);
      if (message != null) {
        setState(() => _step = i);
        _toast(message);
        return;
      }
    }
    final saved = await _persist(step: 5, validate: false);
    if (!saved || _admissionId == null) return;
    setState(() {
      _busy = true;
      _submittedLock = true;
    });
    try {
      final record = await _api!.submit(_admissionId!);
      if (!mounted) return;
      setState(() {
        _applyRecord(record);
        _busy = false;
        _step = 4;
      });
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Admission submitted'),
          content: const Text(
            'The admission has been submitted successfully.',
          ),
          actions: [
            FilledButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('OK'),
            ),
          ],
        ),
      );
      if (mounted) context.go('/admissions/submitted');
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _submittedLock = false;
      });
      _toast('$error');
    }
  }

  Future<void> _onDistrictChanged(int? districtId) async {
    setState(() {
      _districtId = districtId;
      _talukaId = null;
      _talukas = const [];
    });
    if (districtId == null) return;
    try {
      final talukas = await _api!.talukas(districtId);
      if (!mounted) return;
      setState(() => _talukas = talukas);
    } catch (error) {
      _toast('$error');
    }
  }

  Future<void> _pickDocument(String type) async {
    if (!_editable) return;
    final files = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    );
    if (files.isEmpty) return;
    var path = files.first.path;
    if (path == null) {
      final bytes = await files.first.readAsBytes();
      final dir = await getTemporaryDirectory();
      final tmp = File('${dir.path}/${files.first.name}');
      await tmp.writeAsBytes(bytes, flush: true);
      path = tmp.path;
    }
    if (_admissionId == null) {
      final ok = await _persist(step: 4, validate: false);
      if (!ok) return;
    }
    setState(() {
      _uploadType = type;
      _uploadProgress[type] = 0;
    });
    try {
      final record = await _api!.uploadDocument(
        admissionId: _admissionId!,
        documentType: type,
        filePath: path,
        onProgress: (sent, total) {
          if (total <= 0 || !mounted) return;
          setState(() => _uploadProgress[type] = sent / total);
        },
      );
      if (!mounted) return;
      setState(() {
        _record = record;
        _uploadType = null;
        _uploadProgress.remove(type);
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _uploadType = null;
        _uploadProgress.remove(type);
      });
      _toast('$error');
    }
  }

  Future<void> _removeDocument(AdmissionDocumentInfo document) async {
    if (!_editable || _admissionId == null) return;
    try {
      final record = await _api!.removeDocument(
        admissionId: _admissionId!,
        documentId: document.id,
      );
      if (!mounted) return;
      setState(() => _record = record);
    } catch (error) {
      _toast('$error');
    }
  }

  Future<void> _preview(AdmissionDocumentInfo document) async {
    if (_admissionId == null) return;
    try {
      final bytes = await _api!.downloadDocument(
        admissionId: _admissionId!,
        documentId: document.id,
      );
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/${document.originalName}');
      await file.writeAsBytes(Uint8List.fromList(bytes), flush: true);
      await OpenFilex.open(file.path);
    } catch (error) {
      _toast('$error');
    }
  }

  void _toast(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: _editable ? 'New Admission' : 'Admission',
      showBack: true,
      body: _loading
          ? const PgLoadingState()
          : _error != null
          ? PgErrorState(message: _error!, onRetry: _bootstrap)
          : Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(
                    AppSpacing.screenPadding,
                    AppSpacing.sm,
                    AppSpacing.screenPadding,
                    AppSpacing.md,
                  ),
                  child: AdmissionStepIndicator(
                    current: _step,
                    labels: _steps,
                  ),
                ),
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.screenPadding,
                    ),
                    children: [
                      if (!_editable)
                        Padding(
                          padding: const EdgeInsets.only(bottom: AppSpacing.md),
                          child: Text(
                            'This admission has been submitted and cannot be edited.',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ),
                      _stepBody(),
                      const SizedBox(height: 120),
                    ],
                  ),
                ),
                _footer(),
              ],
            ),
    );
  }

  Widget _stepBody() {
    return switch (_step) {
      0 => _schemeStep(),
      1 => _personalStep(),
      2 => _addressStep(),
      3 => _documentsStep(),
      _ => _reviewStep(),
    };
  }

  Widget _schemeStep() {
    if (_schemes.isEmpty) {
      return const PgEmptyState(
        message: 'No active schemes are available. Ask the Admin to add a scheme.',
      );
    }
    return PgCard(
      child: DropdownButtonFormField<int>(
        key: ValueKey('scheme-$_schemeId-$_editable'),
        initialValue: _schemes.any((item) => item.id == _schemeId) ? _schemeId : null,
        decoration: const InputDecoration(labelText: 'Scheme Name'),
        items: [
          for (final scheme in _schemes)
            DropdownMenuItem(value: scheme.id, child: Text(scheme.name)),
        ],
        onChanged: !_editable
            ? null
            : (value) => setState(() => _schemeId = value),
      ),
    );
  }

  Widget _personalStep() {
    return PgCard(
      child: Column(
        children: [
          TextField(
            controller: _firstName,
            enabled: _editable,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(labelText: 'First Name'),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _middleName,
            enabled: _editable,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(labelText: 'Middle Name'),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _lastName,
            enabled: _editable,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(labelText: 'Last Name'),
          ),
          const SizedBox(height: AppSpacing.md),
          _dropdown(
            label: 'Gender',
            value: _gender,
            items: _lookups.genders,
            onChanged: (value) => setState(() => _gender = value),
          ),
          const SizedBox(height: AppSpacing.md),
          _dropdown(
            label: 'Religion',
            value: _religion,
            items: _lookups.religions,
            onChanged: (value) => setState(() => _religion = value),
          ),
          const SizedBox(height: AppSpacing.md),
          _dropdown(
            label: 'Caste',
            value: _caste,
            items: _lookups.castes,
            onChanged: (value) => setState(() => _caste = value),
          ),
        ],
      ),
    );
  }

  Widget _addressStep() {
    return PgCard(
      child: Column(
        children: [
          InputDecorator(
            decoration: const InputDecoration(labelText: 'State'),
            child: Text(_lookups.state),
          ),
          const SizedBox(height: AppSpacing.md),
          DropdownButtonFormField<int>(
            key: ValueKey('district-${_lookups.districts.length}-$_districtId-$_editable'),
            initialValue: _lookups.districts.any((item) => item.id == _districtId)
                ? _districtId
                : null,
            decoration: const InputDecoration(
              labelText: 'District',
              hintText: 'Select district',
            ),
            items: [
              for (final district in _lookups.districts)
                DropdownMenuItem(value: district.id, child: Text(district.name)),
            ],
            onChanged: !_editable ? null : _onDistrictChanged,
          ),
          const SizedBox(height: AppSpacing.md),
          DropdownButtonFormField<int>(
            key: ValueKey('taluka-${_talukas.length}-$_talukaId-$_districtId-$_editable'),
            initialValue: _talukas.any((item) => item.id == _talukaId) ? _talukaId : null,
            decoration: const InputDecoration(
              labelText: 'Taluka',
              hintText: 'Select taluka',
            ),
            items: [
              for (final taluka in _talukas)
                DropdownMenuItem(value: taluka.id, child: Text(taluka.name)),
            ],
            onChanged: !_editable
                ? null
                : (value) => setState(() => _talukaId = value),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _village,
            enabled: _editable,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(labelText: 'Village'),
          ),
        ],
      ),
    );
  }

  Widget _documentsStep() {
    return Column(
      children: [
        for (final slot in _documentSlots) ...[
          _documentCard(slot.$1, slot.$2, slot.$3),
          const SizedBox(height: AppSpacing.md),
        ],
      ],
    );
  }

  Widget _documentCard(String type, String label, bool required) {
    final document = _record?.documentOf(type);
    final progress = _uploadProgress[type];
    return PgCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            required ? '$label *' : label,
            style: Theme.of(context).textTheme.titleSmall,
          ),
          const SizedBox(height: 8),
          if (document == null)
            Text(
              'No file uploaded',
              style: Theme.of(context).textTheme.bodySmall,
            )
          else ...[
            Text(document.originalName, style: Theme.of(context).textTheme.bodyMedium),
            const SizedBox(height: 4),
            Text(
              'Uploaded',
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: AppColors.success,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
          if (progress != null) ...[
            const SizedBox(height: 8),
            LinearProgressIndicator(value: progress == 0 ? null : progress),
          ],
          const SizedBox(height: AppSpacing.sm),
          Wrap(
            spacing: 8,
            children: [
              if (_editable)
                FilledButton.tonal(
                  onPressed: _uploadType == type ? null : () => _pickDocument(type),
                  child: Text(document == null ? 'Upload' : 'Replace'),
                ),
              if (document != null)
                TextButton(
                  onPressed: () => _preview(document),
                  child: const Text('Preview'),
                ),
              if (_editable && document != null)
                TextButton(
                  onPressed: () => _removeDocument(document),
                  child: const Text('Remove'),
                ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _reviewStep() {
    final schemeName = _named(_schemes, _schemeId) ?? _record?.schemeName;
    final districtName =
        _named(_lookups.districts, _districtId) ?? _record?.districtName;
    final talukaName = _named(_talukas, _talukaId) ?? _record?.talukaName;
    final fullName = [
      _firstName.text.trim(),
      _middleName.text.trim(),
      _lastName.text.trim(),
    ].where((part) => part.isNotEmpty).join(' ');

    return PgCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _reviewRow('Scheme', schemeName ?? '-'),
          _reviewRow('Full Name', fullName.isEmpty ? '-' : fullName),
          _reviewRow('Gender', _gender ?? '-'),
          _reviewRow('Religion', _religion ?? '-'),
          _reviewRow('Caste', _caste ?? '-'),
          _reviewRow(
            'Address',
            [
              _village.text.trim(),
              ?talukaName,
              ?districtName,
              _lookups.state,
            ].where((part) => part.isNotEmpty).join(', '),
          ),
          const Divider(height: 24),
          Text('Documents', style: Theme.of(context).textTheme.titleSmall),
          const SizedBox(height: 8),
          for (final slot in _documentSlots)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Text(
                '${slot.$2}: ${_record?.documentOf(slot.$1)?.originalName ?? 'Not uploaded'}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
          if (_editable)
            TextButton(
              onPressed: () => setState(() => _step = 0),
              child: const Text('Edit from start'),
            ),
        ],
      ),
    );
  }

  String? _named(List<NamedLookup> items, int? id) {
    for (final item in items) {
      if (item.id == id) return item.name;
    }
    return null;
  }

  Widget _reviewRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: Theme.of(context).textTheme.labelSmall),
          Text(value, style: Theme.of(context).textTheme.titleSmall),
        ],
      ),
    );
  }

  Widget _dropdown({
    required String label,
    required String? value,
    required List<String> items,
    required ValueChanged<String?> onChanged,
  }) {
    return DropdownButtonFormField<String>(
      key: ValueKey('$label-$value-$_editable'),
      initialValue: items.contains(value) ? value : null,
      decoration: InputDecoration(labelText: label),
      items: [
        for (final item in items)
          DropdownMenuItem(value: item, child: Text(item)),
      ],
      onChanged: !_editable ? null : onChanged,
    );
  }

  Widget _footer() {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.screenPadding,
          8,
          AppSpacing.screenPadding,
          12,
        ),
        child: Row(
          children: [
            if (_step > 0)
              Expanded(
                child: OutlinedButton(
                  onPressed: _busy ? null : () => setState(() => _step -= 1),
                  child: const Text('Back'),
                ),
              ),
            if (_step > 0) const SizedBox(width: 8),
            if (_editable)
              Expanded(
                child: OutlinedButton(
                  onPressed: _busy ? null : _saveDraft,
                  child: const Text('Save as Draft'),
                ),
              ),
            if (_editable) const SizedBox(width: 8),
            Expanded(
              child: FilledButton(
                onPressed: _busy || (_step == 4 && !_editable)
                    ? null
                    : _next,
                child: _busy
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text(_step == 4 ? 'Submit' : 'Next'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
