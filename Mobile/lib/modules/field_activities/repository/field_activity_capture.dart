import 'package:geocoding/geocoding.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import 'package:permission_handler/permission_handler.dart' as ph;

import '../../attendance/models/attendance_format.dart';
import '../../attendance/repository/geotag_stamp.dart';
import '../api/field_activity_api.dart';

class FieldActivityCaptureResult {
  const FieldActivityCaptureResult({
    required this.latitude,
    required this.longitude,
    required this.location,
    required this.photoPath,
    required this.capturedAt,
  });

  final double latitude;
  final double longitude;
  final String location;
  final String photoPath;
  final DateTime capturedAt;
}

class FieldActivityCapture {
  Future<FieldActivityCaptureResult> capture() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const FieldActivityApiException('Please enable GPS to continue.');
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw const FieldActivityApiException(
        'Location permission is required. Enable it in app settings.',
      );
    }
    final camera = await ph.Permission.camera.request();
    if (!camera.isGranted) {
      throw const FieldActivityApiException(
        'Camera permission is required. Enable it in app settings.',
      );
    }

    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        timeLimit: Duration(seconds: 20),
      ),
    );

    var address = '';
    try {
      final place = (await placemarkFromCoordinates(
        position.latitude,
        position.longitude,
      )).first;
      address = [
        place.name,
        place.street,
        place.locality,
        place.subAdministrativeArea,
        place.administrativeArea,
        place.postalCode,
      ].where((value) => value != null && value.isNotEmpty).join(', ');
    } catch (_) {}
    if (address.trim().isEmpty) {
      address =
          '${position.latitude.toStringAsFixed(6)}, ${position.longitude.toStringAsFixed(6)}';
    }

    // Camera capture only — never ImageSource.gallery / file picker.
    final image = await ImagePicker().pickImage(
      source: ImageSource.camera,
      preferredCameraDevice: CameraDevice.rear,
      imageQuality: 78,
      maxWidth: 1440,
    );
    if (image == null) {
      throw const FieldActivityApiException(
        'A live camera photo is required to submit an activity.',
      );
    }

    final capturedAt = AttendanceFormat.istNow();
    String photoPath;
    try {
      photoPath = await GeotagStamp.burnIntoPhoto(
        photoPath: image.path,
        latitude: position.latitude,
        longitude: position.longitude,
        capturedAtIst: capturedAt,
        address: address,
      );
    } catch (_) {
      throw const FieldActivityApiException(
        'Unable to geotag the photo. Please try again.',
      );
    }

    return FieldActivityCaptureResult(
      latitude: position.latitude,
      longitude: position.longitude,
      location: address,
      photoPath: photoPath,
      capturedAt: capturedAt,
    );
  }
}
