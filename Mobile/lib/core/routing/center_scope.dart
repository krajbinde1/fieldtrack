int? parseCenterId(String? value) => int.tryParse(value ?? '');

String withCenterId(String path, int? centerId) {
  if (centerId == null) return path;
  return path.contains('?') ? '$path&center_id=$centerId' : '$path?center_id=$centerId';
}
