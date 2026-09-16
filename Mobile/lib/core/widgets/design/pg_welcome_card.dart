import 'package:flutter/material.dart';
import '../../design/app_spacing.dart';
import 'pg_card.dart';

class PgWelcomeCard extends StatelessWidget {
  const PgWelcomeCard({
    super.key,
    required this.name,
    required this.dateLabel,
    this.photoUrl,
    this.role,
    this.padding = const EdgeInsets.all(AppSpacing.lg),
    this.avatarRadius = 32,
    this.prominent = false,
  });

  final String name;
  final String dateLabel;
  final String? photoUrl;
  final String? role;
  final EdgeInsetsGeometry padding;
  final double avatarRadius;
  final bool prominent;

  @override
  Widget build(BuildContext context) => PgCard(
    gradient: const LinearGradient(
      colors: [Color(0xFF0F766E), Color(0xFF14B8A6)],
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
    ),
    padding: padding,
    child: Row(
      children: [
        Container(
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white.withValues(alpha: 0.4), width: 2),
          ),
          child: CircleAvatar(
            radius: avatarRadius,
            backgroundColor: Colors.white.withValues(alpha: 0.2),
            backgroundImage: photoUrl != null ? NetworkImage(photoUrl!) : null,
            child: photoUrl == null
                ? Text(
                    name.isNotEmpty ? name[0].toUpperCase() : '?',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: avatarRadius * 0.72,
                      fontWeight: FontWeight.w700,
                    ),
                  )
                : null,
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Welcome back,',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Colors.white.withValues(alpha: 0.85),
                ),
              ),
              if (prominent) const SizedBox(height: 2),
              Text(
                name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: (prominent
                        ? Theme.of(context).textTheme.headlineSmall
                        : Theme.of(context).textTheme.titleLarge)
                    ?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  height: 1.15,
                ),
              ),
              if (role != null) ...[
                const SizedBox(height: 2),
                Text(
                  role!,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Colors.white.withValues(alpha: 0.75),
                  ),
                ),
              ],
              SizedBox(height: prominent ? 10 : 6),
              Row(
                children: [
                  IconTheme(
                    data: IconThemeData(
                      size: prominent ? 16 : 14,
                      color: Colors.white.withValues(alpha: 0.8),
                    ),
                    child: const Icon(Icons.calendar_today_rounded),
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      dateLabel,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: (prominent
                              ? Theme.of(context).textTheme.bodyMedium
                              : Theme.of(context).textTheme.bodySmall)
                          ?.copyWith(
                        color: Colors.white.withValues(alpha: 0.9),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    ),
  );
}
