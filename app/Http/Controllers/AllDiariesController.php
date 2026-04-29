<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AllDiariesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $feelings = $user->feelingsDiaries()->get()->map(function ($item) {
            $item->type = 'feelings';
            $item->display_title = $item->situation;
            return $item;
        });

        $personal = $user->personalDiaries()->get()->map(function ($item) {
            $item->type = 'personal';
            $item->display_title = $item->title;
            return $item;
        });

        $food = $user->foodDiaries()->with('media')->get()->map(function ($item) {
            $item->type = 'food';
            $item->display_title = $item->title;
            return $item;
        });

        $allDiaries = $feelings->concat($personal)->concat($food)->sortByDesc('created_at')->values();

        if ($request->filled('search')) {
            $search = mb_strtolower($request->search);
            $allDiaries = $allDiaries->filter(function ($item) use ($search) {
                return mb_strpos(mb_strtolower($item->display_title ?? ''), $search) !== false ||
                    mb_strpos(mb_strtolower($item->content ?? ''), $search) !== false ||
                    mb_strpos(mb_strtolower($item->thoughts ?? ''), $search) !== false;
            });
        }

        $perPage = 10;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $allDiaries->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginated = new LengthAwarePaginator(
            $currentItems,
            $allDiaries->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'success' => true,
            'data' => $paginated
        ]);
    }

    public function trash(Request $request)
    {
        $user = $request->user();

        $feelings = $user->feelingsDiaries()->onlyTrashed()->get()->map(function ($item) {
            $item->type = 'feelings';
            $item->display_title = $item->situation;
            return $item;
        });

        $personal = $user->personalDiaries()->onlyTrashed()->get()->map(function ($item) {
            $item->type = 'personal';
            $item->display_title = $item->title;
            return $item;
        });

        $food = $user->foodDiaries()->onlyTrashed()->with('media')->get()->map(function ($item) {
            $item->type = 'food';
            $item->display_title = $item->title;
            return $item;
        });

        $allTrash = $feelings->concat($personal)->concat($food)->sortByDesc('deleted_at')->values();

        if ($request->filled('search')) {
            $search = mb_strtolower($request->search);
            $allTrash = $allTrash->filter(function ($item) use ($search) {
                return mb_strpos(mb_strtolower($item->display_title ?? ''), $search) !== false;
            });
        }

        return response()->json([
            'success' => true,
            'data' => ['data' => $allTrash]
        ]);
    }
}
