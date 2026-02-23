<?php

namespace Sosupp\SlimerDesktop\Services;

use Illuminate\Support\Facades\Http;

class GithubReleaseService
{
    protected string $owner;
    protected string $repo;
    protected string $token;
    protected string $api;

    public function __construct()
    {
        $this->owner = config('slimerdesktop.release.github.owner');
        $this->repo  = config('slimerdesktop.release.github.repo');
        $this->token = config('slimerdesktop.release.github.token');
        $this->api   = "https://api.github.com/repos/{$this->owner}/{$this->repo}";
    }

    protected function client()
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'NativePHP-Updater',
            ]);
    }

    public function findDraftByTag(string $tag)
    {
        $res = $this->client()->get("{$this->api}/releases");

        $res->throw();

        return collect($res->json())->first(fn ($r) =>
            $r['draft'] === true && $r['tag_name'] === $tag
        );
    }

    public function createDraft(string $tag, string $title)
    {
        $res = $this->client()->post("{$this->api}/releases", [
            'tag_name' => $tag,
            'name'     => $title,
            'draft'    => true,
            'prerelease' => false,
        ]);

        $res->throw();

        return $res->json();
    }

    public function ensureDraft(string $tag)
    {
        if ($draft = $this->findDraftByTag($tag)) {
            return $draft;
        }

        return $this->createDraft($tag, "{$tag}");
    }
}
