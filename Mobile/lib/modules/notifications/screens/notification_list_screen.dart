import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../expenses/api/expense_api.dart';

class NotificationListScreen extends StatefulWidget {
  const NotificationListScreen({super.key});

  @override
  State<NotificationListScreen> createState() => _NotificationListScreenState();
}

class _NotificationListScreenState extends State<NotificationListScreen> {
  late Future<({int unread, List<Map<String, dynamic>> items})> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<({int unread, List<Map<String, dynamic>> items})> _load() async {
    return (await ExpenseApi.create()).notifications();
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Notifications',
      showBack: true,
      body: FutureBuilder(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const PgLoadingState();
          }
          if (snapshot.hasError) {
            return PgErrorState(message: '${snapshot.error}', onRetry: () => setState(() => _future = _load()));
          }
          final items = snapshot.data?.items ?? const <Map<String, dynamic>>[];
          if (items.isEmpty) {
            return const PgEmptyState(message: 'No notifications yet.');
          }
          return ListView.separated(
            padding: const EdgeInsets.all(AppSpacing.screenPadding),
            itemCount: items.length,
            separatorBuilder: (_, _) => const Divider(height: 1),
            itemBuilder: (context, index) {
              final item = items[index];
              final data = item['data'] is Map ? Map<String, dynamic>.from(item['data'] as Map) : const <String, dynamic>{};
              return ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('${item['title'] ?? ''}'),
                subtitle: Text('${item['body'] ?? ''}'),
                trailing: item['read'] == true ? null : const Icon(Icons.circle, size: 10, color: Colors.deepPurple),
                onTap: () async {
                  final id = int.tryParse('${item['id']}');
                  if (id != null) {
                    await (await ExpenseApi.create()).markRead(id);
                  }
                  final route = '${data['route'] ?? ''}';
                  if (route.startsWith('/') && context.mounted) {
                    context.push(route);
                  }
                },
              );
            },
          );
        },
      ),
    );
  }
}
