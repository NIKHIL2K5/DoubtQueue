<?php

declare(strict_types=1);

namespace DoubtQueue\Controller;

use DoubtQueue\Http\Request;
use DoubtQueue\Http\Response;
use DoubtQueue\Service\DoubtService;

class DoubtController
{
    public function __construct(private DoubtService $doubts)
    {
    }

    public function join(Request $request, array $params): Response
    {
        return new Response(201, $this->doubts->join($params['id'], $request->body()));
    }

    public function queue(Request $request, array $params): Response
    {
        return new Response(
            200,
            $this->doubts->queue(
                $params['id'],
                $request->query['limit'] ?? null,
                $request->query['offset'] ?? null
            )
        );
    }

    public function next(Request $request, array $params): Response
    {
        return new Response(200, $this->doubts->next($params['id']));
    }

    public function stats(Request $request, array $params): Response
    {
        return new Response(200, $this->doubts->stats($params['id']));
    }

    public function get(Request $request, array $params): Response
    {
        return new Response(200, $this->doubts->get($params['id']));
    }

    public function solved(Request $request, array $params): Response
    {
        return new Response(200, $this->doubts->markSolved($params['id']));
    }

    public function skipped(Request $request, array $params): Response
    {
        return new Response(200, $this->doubts->markSkipped($params['id']));
    }
}
