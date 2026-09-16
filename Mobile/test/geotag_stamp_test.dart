import 'package:fieldtrack/modules/attendance/repository/geotag_stamp.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('geotag stamp includes address, coordinates, IST date and time', () {
    final lines = GeotagStamp.lines(
      latitude: 18.520123,
      longitude: 73.850987,
      capturedAtIst: DateTime(2026, 9, 17, 13, 5, 0),
      address: 'FC Road, Pune',
    );

    expect(lines[0], 'FC Road, Pune');
    expect(lines[1], 'Lat 18.520123, Lng 73.850987');
    expect(lines[2], 'Date 17 Sep 2026');
    expect(lines[3], 'Time 01:05 PM IST');
  });

  test('geotag stamp still prints coords and IST time when address is empty', () {
    final lines = GeotagStamp.lines(
      latitude: 18.52,
      longitude: 73.85,
      capturedAtIst: DateTime(2026, 9, 17, 9, 0, 0),
      address: '  ',
    );

    expect(lines, isNot(contains('')));
    expect(lines.first, startsWith('Lat '));
    expect(lines.last, 'Time 09:00 AM IST');
  });
}
