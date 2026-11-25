class PresenceEvent {
  const PresenceEvent({
    required this.localUuid,
    required this.attendanceId,
    required this.type,
    required this.recordedAt,
    this.latitude,
    this.longitude,
  });

  static const gpsOff = 'gps_off';
  static const gpsOn = 'gps_on';
  static const internetOff = 'internet_off';
  static const internetOn = 'internet_on';

  final String localUuid;
  final int attendanceId;
  final String type;
  final String recordedAt;
  final double? latitude;
  final double? longitude;

  Map<String, dynamic> toJson() => {
    'local_uuid': localUuid,
    'attendance_id': attendanceId,
    'event_type': type,
    'recorded_at': recordedAt,
    'latitude': latitude,
    'longitude': longitude,
  };

  factory PresenceEvent.fromJson(Map<String, dynamic> json) => PresenceEvent(
    localUuid: json['local_uuid']?.toString() ?? '',
    attendanceId: int.tryParse('${json['attendance_id'] ?? ''}') ?? 0,
    type: json['event_type']?.toString() ?? '',
    recordedAt: json['recorded_at']?.toString() ?? '',
    latitude: json['latitude'] == null
        ? null
        : double.tryParse('${json['latitude']}'),
    longitude: json['longitude'] == null
        ? null
        : double.tryParse('${json['longitude']}'),
  );

  Map<String, dynamic> toApi() => {
    'local_uuid': localUuid,
    'event_type': type,
    'recorded_at': recordedAt,
    if (latitude != null) 'latitude': latitude,
    if (longitude != null) 'longitude': longitude,
  };
}
