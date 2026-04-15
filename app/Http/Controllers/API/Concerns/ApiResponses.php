<?php

namespace App\Http\Controllers\API\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

trait ApiResponses
{
    // ! JSON Response
    public static function jsonResponse(
        bool $status,
        string $message,
        int $code,
        mixed $data = null,
        bool $paginate = false,
        mixed $paginateData = null,
    ): JsonResponse {
        $response = [
            'status' => $status,
            'message' => $message,
            'code' => $code,
        ];

        if ($paginate === true) {
            $paginator = null;

            if ($paginateData instanceof LengthAwarePaginator || $paginateData instanceof Paginator) {
                $paginator = $paginateData;
                $response['data'] = $data;
            } elseif ($data instanceof LengthAwarePaginator || $data instanceof Paginator) {
                $paginator = $data;
                $response['data'] = method_exists($data, 'items') ? $data->items() : $data->toArray();
            }

            if ($paginator !== null) {
                $response['pagination'] = [
                    'current_page' => method_exists($paginator, 'currentPage') ? $paginator->currentPage() : null,
                    'last_page' => method_exists($paginator, 'lastPage') ? $paginator->lastPage() : null,
                    'per_page' => method_exists($paginator, 'perPage') ? $paginator->perPage() : null,
                    'total' => method_exists($paginator, 'total') ? $paginator->total() : null,
                    'first_page_url' => method_exists($paginator, 'url') ? $paginator->url(1) : null,
                    'last_page_url' => method_exists($paginator, 'lastPage') ? $paginator->url($paginator->lastPage()) : null,
                    'next_page_url' => method_exists($paginator, 'nextPageUrl') ? $paginator->nextPageUrl() : null,
                    'prev_page_url' => method_exists($paginator, 'previousPageUrl') ? $paginator->previousPageUrl() : null,
                    'from' => method_exists($paginator, 'firstItem') ? $paginator->firstItem() : null,
                    'to' => method_exists($paginator, 'lastItem') ? $paginator->lastItem() : null,
                    'path' => method_exists($paginator, 'path') ? $paginator->path() : null,
                ];
            } elseif ($data !== null) {
                $response['data'] = $data;
            }
        } elseif ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    public static function jsonErrorResponse(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        $response = [
            'status' => false,
            'message' => $message,
            'code' => $code,
            'errors' => $errors,
        ];

        return response()->json($response, $code);
    }
}
