import 'dart:convert';
import 'dart:async';

import 'package:doubtqueue/services/api_service.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  const baseUrl = 'http://test.local';

  test('getSessions returns sessions from the API', () async {
    final client = MockClient((request) async {
      expect(request.url.toString(), '$baseUrl/api/sessions?status=OPEN');
      return http.Response(
        jsonEncode([
          {
            'id': 1,
            'title': 'PHP Basics Doubt Session',
            'mentor_name': 'Priya',
            'status': 'OPEN',
            'waiting_count': 5,
          },
        ]),
        200,
      );
    });
    final api = ApiService(client, baseUrl: baseUrl);

    final sessions = await api.getSessions(status: 'OPEN');

    expect(sessions, hasLength(1));
    expect(sessions.first.title, 'PHP Basics Doubt Session');
    expect(sessions.first.waitingCount, 5);
  });

  test('a network failure throws a friendly message', () async {
    final client = MockClient((request) async {
      throw http.ClientException('connection refused');
    });
    final api = ApiService(client, baseUrl: baseUrl);

    expect(
      api.getSessions(),
      throwsA(
        isA<ApiException>().having(
          (e) => e.message,
          'message',
          contains('Could not reach the server'),
        ),
      ),
    );
  });

  test('getSessions without a status asks for every session', () async {
    final client = MockClient((request) async {
      expect(request.url.toString(), '$baseUrl/api/sessions');
      return http.Response('[]', 200);
    });
    final api = ApiService(client, baseUrl: baseUrl);

    expect(await api.getSessions(), isEmpty);
  });

    test('a request timeout throws a friendly ApiException', () async {
    final completer = Completer<http.Response>();

    final client = MockClient((request) => completer.future);
    final api = ApiService(
      client,
      baseUrl: baseUrl,
      timeout: const Duration(milliseconds: 10),
    );

    await expectLater(
      api.getSessions(),
      throwsA(
        isA<ApiException>().having(
          (error) => error.message,
          'message',
          'The server took too long to answer. Try again.',
        ),
      ),
    );

    client.close();
  });

  test('a 409 response throws an exception with the API message', () async {
    final client = MockClient((request) async {
      return http.Response(
        jsonEncode({'status': 409, 'message': 'Session with id 2 is closed'}),
        409,
      );
    });
    final api = ApiService(client, baseUrl: baseUrl);

    expect(
      api.joinSession(
        2,
        name: 'Asha',
        email: 'asha@example.com',
        topic: 'Arrays',
        question: 'How do I loop?',
      ),
      throwsA(
        isA<ApiException>().having(
          (e) => e.message,
          'message',
          contains('Session with id 2 is closed'),
        ),
      ),
    );
  });
}
