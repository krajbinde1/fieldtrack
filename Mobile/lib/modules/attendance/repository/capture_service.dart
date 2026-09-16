import 'package:geocoding/geocoding.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import 'package:permission_handler/permission_handler.dart' as ph;
import '../api/attendance_api_service.dart';
import '../models/attendance_format.dart';
import '../models/punch_capture.dart';
import 'geotag_stamp.dart';

class CaptureService {
  Future<PunchCapture> capture() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const AttendanceApiException('Please enable GPS to continue.');
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw const AttendanceApiException(
        'Location permission is required. Enable it in app settings.',
      );
    }
    final camera = await ph.Permission.camera.request();
    if (!camera.isGranted) {
      throw const AttendanceApiException(
        'Camera permission is required. Enable it in app settings.',
      );
    }
    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        timeLimit: Duration(seconds: 20),
      ),
    );
    String address;
    try {
      final p = (await placemarkFromCoordinates(
        position.latitude,
        position.longitude,
      )).first;
      address = [
        p.name,
        p.street,
        p.locality,
        p.administrativeArea,
        p.postalCode,
      ].where((e) => e != null && e.isNotEmpty).join(', ');
    } catch (_) {
      throw const AttendanceApiException(
        'Unable to determine the address. Please try again outdoors.',
      );
    }
    if (address.trim().isEmpty) {
      address =
          '${position.latitude.toStringAsFixed(6)}, ${position.longitude.toStringAsFixed(6)}';
    }
    // Camera capture only — never ImageSource.gallery / file picker.
    final image = await ImagePicker().pickImage(
      source: ImageSource.camera,
      preferredCameraDevice: CameraDevice.front,
      imageQuality: 78,
      maxWidth: 1440,
    );
    if (image == null) {
      throw const AttendanceApiException(
        'A live camera photo is required to Punch In/Out.',
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
      throw const AttendanceApiException(
        'Unable to geotag the photo. Please try again.',
      );
    }

    return PunchCapture(
      latitude: position.latitude,
      longitude: position.longitude,
      address: address,
      photoPath: photoPath,
      capturedAt: capturedAt,
    );
  }
}
