import 'dart:convert';
import 'dart:async';

import 'package:doubtqueue/services/api_service.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

/// A small in-memory copy of the DoubtQueue API for widget tests.
class FakeBackend {
  FakeBackend({List<Map<String, dynamic>>? sessions})
    : sessions = sessions ?? [openSession(1, 'PHP Basics Doubt Session')];

  final List<Map<String, dynamic>> sessions;
  final List<Map<String, dynamic>> doubts = [];
  bool failRequests = false;
  bool timeoutRequests = false;

  static Map<String, dynamic> openSession(
    int id,
    String title, {
    String mentor = 'Priya',
    String createdAt = '2026-01-01 10:00:00',
  }) {
    return {
      'id': id,
      'title': title,
      'mentor_name': mentor,
      'status': 'OPEN',
      'created_at': createdAt,
      'closed_at': null,
    };
  }

  late final MockClient client = MockClient(_handle);

  ApiService get api => ApiService(client, baseUrl: 'http://test.local');

  Map<String, dynamic> addDoubt(
    int sessionId,
    String name, {
    String status = 'WAITING',
  }) {
    final doubt = {
      'id': doubts.length + 1,
      'session_id': sessionId,
      'student_name': name,
      'student_email': '${name.toLowerCase()}@example.com',
      'topic': 'Arrays',
      'question': 'How do loops work for $name?',
      'status': status,
      'created_at': '2026-01-01 10:05:00',
      'started_at': null,
      'finished_at': null,
    };
    doubts.add(doubt);
    return doubt;
  }

  List<Map<String, dynamic>> _doubtsOf(int sessionId, String status) {
    return doubts
        .where((d) => d['session_id'] == sessionId && d['status'] == status)
        .toList();
  }

  Map<String, dynamic> _withPosition(Map<String, dynamic> doubt) {
    final waiting = _doubtsOf(doubt['session_id'] as int, 'WAITING');
    final isWaiting = doubt['status'] == 'WAITING';
    return {
      ...doubt,
      'position': isWaiting ? waiting.indexOf(doubt) + 1 : null,
    };
  }

  Map<String, dynamic> _sessionJson(Map<String, dynamic> session) {
    final waiting = _doubtsOf(session['id'] as int, 'WAITING').length;
    return {...session, 'waiting_count': waiting};
  }

  http.Response _json(Object body, [int status = 200]) {
    return http.Response(jsonEncode(body), status);
  }

  Future<http.Response> _handle(http.Request request) async {
    if (timeoutRequests) {
      return Completer<http.Response>().future;
    }

    if (failRequests) {
      return _json({'status': 500, 'message': 'Something went wrong'}, 500);
    }

    final path = request.url.path;
    final method = request.method;

    if (path == '/api/sessions' && method == 'GET') {
      final status = request.url.queryParameters['status'];
      final list = sessions.where(
        (s) => status == null || s['status'] == status,
      );
      return _json(list.map(_sessionJson).toList());
    }
    if (path == '/api/sessions' && method == 'POST') {
      final body = jsonDecode(request.body) as Map<String, dynamic>;
      final session = {
        ...openSession(sessions.length + 1, body['title'] as String),
        'mentor_name': body['mentor_name'],
      };
      sessions.add(session);
      return _json(_sessionJson(session), 201);
    }

    final sessionMatch = RegExp(
      r'^/api/sessions/(\d+)/(doubts|queue|stats|next|close)$',
    ).firstMatch(path);
    if (sessionMatch != null) {
      return _handleSession(
        int.parse(sessionMatch.group(1)!),
        sessionMatch.group(2)!,
        request,
      );
    }

    final doubtMatch = RegExp(r'^/api/doubts/(\d+)(/solved|/skipped)?$')
        .firstMatch(path);
    if (doubtMatch != null) {
      return _handleDoubt(int.parse(doubtMatch.group(1)!), doubtMatch.group(2));
    }

    return _json({'status': 404, 'message': 'Route not found'}, 404);
  }

  http.Response _handleSession(int id, String action, http.Request request) {
    final session = sessions.firstWhere((s) => s['id'] == id);

    switch (action) {
      case 'doubts':
        if (session['status'] != 'OPEN') {
          return _json({
            'status': 409,
            'message': 'Session with id $id is closed',
          }, 409);
        }
        final body = jsonDecode(request.body) as Map<String, dynamic>;
        final doubt = addDoubt(id, body['student_name'] as String);
        doubt['topic'] = body['topic'];
        doubt['question'] = body['question'];
        return _json(_withPosition(doubt), 201);
      case 'queue':
        return _json(_doubtsOf(id, 'WAITING'));
      case 'stats':
        return _json({
          for (final s in ['WAITING', 'IN_PROGRESS', 'SOLVED', 'SKIPPED'])
            s: _doubtsOf(id, s).length,
        });
      case 'next':
        final waiting = _doubtsOf(id, 'WAITING');
        if (waiting.isEmpty) {
          return _json({'status': 404, 'message': 'No waiting doubts'}, 404);
        }
        waiting.first['status'] = 'IN_PROGRESS';
        waiting.first['started_at'] = '2026-01-01 10:10:00';
        return _json(_withPosition(waiting.first));
      default:
        session['status'] = 'CLOSED';
        return _json(_sessionJson(session));
    }
  }

  http.Response _handleDoubt(int id, String? action) {
    final doubt = doubts.firstWhere((d) => d['id'] == id);
    if (action == '/solved') doubt['status'] = 'SOLVED';
    if (action == '/skipped') doubt['status'] = 'SKIPPED';
    return _json(_withPosition(doubt));
  }
}
