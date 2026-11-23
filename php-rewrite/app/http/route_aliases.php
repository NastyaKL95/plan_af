<?php

declare(strict_types=1);

/**
 * Человекочитаемые URL (совместимость со старыми маршрутами) → внутренний $uri и $_GET.
 */
function iro_legacy_route_aliases(string $uri): string
{
    if (preg_match('~^/news/(\\d+)/?$~', $uri, $m)) {
        $_GET['id'] = (int) $m[1];
        $uri = '/news/show';
    }
    if (preg_match('~^/contest/(\\d+)/stats/?$~', $uri, $m)) {
        $_GET['contest_id'] = (int) $m[1];
        $uri = '/moderator/contest-stats';
    }
    if (preg_match('~^/contest/(\\d+)/report/export\\.xlsx/?$~', $uri, $m)) {
        $_GET['contest_id'] = (int) $m[1];
        $uri = '/moderator/contest-report-export';
    }
    if (preg_match('~^/contest/(\\d+)/report/?$~', $uri, $m)) {
        $_GET['contest_id'] = (int) $m[1];
        $uri = '/moderator/contest-report';
    }
    if (preg_match('~^/contest/(\\d+)/awards/auto/?$~', $uri, $m)) {
        $_GET['contest_id'] = (int) $m[1];
        $uri = '/moderator/contest-awards-auto';
    }
    if (preg_match('~^/contest/(\\d+)/apply/?$~', $uri, $m)) {
        $_GET['contest_id'] = (int) $m[1];
        $uri = '/contest/apply';
    }
    if (preg_match('~^/contest/(\\d+)/upload-consent/?$~', $uri, $m)) {
        $_GET['contest_id'] = (int) $m[1];
        $uri = '/contest/apply';
    }
    if (preg_match('~^/contest/(\\d+)/moderate-consents/?$~', $uri, $m)) {
        $_GET['contest_id'] = (int) $m[1];
        $uri = '/moderator/consents';
    }
    if (preg_match('~^/contest/(\\d+)/participant-files/download/?$~', $uri, $m)) {
        $_GET['contest_id'] = (int) $m[1];
        $uri = '/moderator/participant-files/download';
    }
    if (preg_match('~^/contest/(\\d+)/participant-files/?$~', $uri, $m)) {
        $_GET['contest_id'] = (int) $m[1];
        $uri = '/moderator/participant-files';
    }
    if (preg_match('~^/award/(\\d+)/edit/?$~', $uri, $m)) {
        $_GET['award_id'] = (int) $m[1];
        $uri = '/moderator/award-edit';
    }
    if (preg_match('~^/award/create/(\\d+)/?$~', $uri, $m)) {
        $_GET['contest_id'] = (int) $m[1];
        $uri = '/moderator/award-create';
    }
    if ($uri === '/award/create' || $uri === '/award/create/') {
        $uri = '/moderator/award-pick-contest';
    }
    if (preg_match('~^/stage-delete/(\\d+)/?$~', $uri, $m)) {
        $_GET['stage_id'] = (int) $m[1];
        $uri = '/moderator/stage/delete';
    }
    if (preg_match('~^/submission/(\\d+)/edit-supplements/?$~', $uri, $m)) {
        $_GET['submission_id'] = (int) $m[1];
        $uri = '/submission/supplements';
    }
    if (preg_match('~^/profile/(\\d+)/?$~', $uri, $m)) {
        $_GET['profile_id'] = (int) $m[1];
        $uri = '/profile/view';
    }
    if (preg_match('~^/contest/(\\d+)/?$~', $uri, $m)) {
        $_GET['id'] = (int) $m[1];
        $uri = '/contest';
    }
    if (preg_match('~^/expert/stages/(\\d+)/submissions/?$~', $uri, $m)) {
        $_GET['stage_id'] = (int) $m[1];
        $uri = '/expert/submissions';
    }
    if (preg_match('~^/submission/(\\d+)/evaluate/?$~', $uri, $m)) {
        $_GET['submission_id'] = (int) $m[1];
        $uri = '/expert/evaluate';
    }
    if (preg_match('~^/notifications/(\\d+)/go/?$~', $uri, $m)) {
        $_GET['id'] = (int) $m[1];
        $uri = '/notifications/go';
    }

    return $uri;
}
