import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/painting.dart';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';

import '../models/attendance_format.dart';

/// Burns a readable geotag bar into a punch photo and returns the stamped file path.
class GeotagStamp {
  static List<String> lines({
    required double latitude,
    required double longitude,
    required DateTime capturedAtIst,
    String address = '',
  }) {
    final trimmed = address.trim();
    return [
      if (trimmed.isNotEmpty) trimmed,
      'Lat ${latitude.toStringAsFixed(6)}, Lng ${longitude.toStringAsFixed(6)}',
      'Date ${AttendanceFormat.date(capturedAtIst)}',
      'Time ${DateFormat('hh:mm a').format(capturedAtIst)} IST',
    ];
  }

  static Future<String> burnIntoPhoto({
    required String photoPath,
    required double latitude,
    required double longitude,
    required DateTime capturedAtIst,
    String address = '',
  }) async {
    final source = File(photoPath);
    if (!source.existsSync()) {
      throw StateError('Captured photo file is missing.');
    }

    final bytes = await source.readAsBytes();
    if (bytes.isEmpty) {
      throw StateError('Captured photo is empty.');
    }

    final codec = await ui.instantiateImageCodec(bytes);
    final frame = await codec.getNextFrame();
    final image = frame.image;
    final width = image.width.toDouble();
    final height = image.height.toDouble();

    final stampLines = lines(
      latitude: latitude,
      longitude: longitude,
      capturedAtIst: capturedAtIst,
      address: address,
    );

    final fontSize = (width / 28).clamp(16.0, 40.0);
    final padding = fontSize * 0.7;
    final maxTextWidth = (width - padding * 2).clamp(80.0, width);

    final paragraphs = <ui.Paragraph>[];
    var textHeight = 0.0;
    for (var i = 0; i < stampLines.length; i++) {
      final builder =
          ui.ParagraphBuilder(
              ui.ParagraphStyle(
                textAlign: TextAlign.left,
                fontSize: fontSize,
                fontWeight: FontWeight.w600,
                maxLines: i == 0 ? 3 : 1,
                ellipsis: '…',
              ),
            )
            ..pushStyle(
              ui.TextStyle(
                color: const Color(0xFFFFFFFF),
                fontSize: fontSize,
                fontWeight: FontWeight.w600,
                height: 1.25,
              ),
            )
            ..addText(stampLines[i]);
      final paragraph = builder.build()
        ..layout(ui.ParagraphConstraints(width: maxTextWidth));
      paragraphs.add(paragraph);
      textHeight += paragraph.height;
    }

    final barHeight = textHeight + padding * 2;
    final barTop = height - barHeight;

    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    canvas.drawImage(image, Offset.zero, Paint());
    canvas.drawRect(
      Rect.fromLTWH(0, barTop, width, barHeight),
      Paint()..color = const Color(0xCC111827),
    );

    var y = barTop + padding;
    for (final paragraph in paragraphs) {
      canvas.drawParagraph(paragraph, Offset(padding, y));
      y += paragraph.height;
    }

    final picture = recorder.endRecording();
    final stamped = await picture.toImage(image.width, image.height);
    picture.dispose();
    final png = await stamped.toByteData(format: ui.ImageByteFormat.png);
    image.dispose();
    stamped.dispose();

    if (png == null) {
      throw StateError('Unable to write geotagged photo.');
    }

    final dir = await getTemporaryDirectory();
    final outPath =
        '${dir.path}/punch_${DateTime.now().millisecondsSinceEpoch}.png';
    await File(outPath).writeAsBytes(png.buffer.asUint8List(), flush: true);
    return outPath;
  }
}
