---
name: show-post-is-array
description: public/show.blade.php passes $post as an ARRAY, not an Eloquent model — Livewire components mounted there take int postId, not public Post $post
metadata:
  type: project
---

`resources/views/public/show.blade.php` consumes `$post` as an **array** (`$post['slug']`, `$post['type']`, `$post['hero_image']`, `<x-share-bar :url="route('posts.show', $post['slug'])">`), not an Eloquent model.

**Why:** the post page is served from a to-array()/cached shape, so any Livewire component mounted on the post page cannot bind `public Post $post`.

**How to apply:** When a plan's literal text says a post-page Livewire component should hold `public Post $post` (e.g. Plan 02 §2.2 WorthItVote), the correct implementation is `#[Locked] public int $postId` with `mount(int $postId)` + `Post::find()` in render, mounted via `@livewire('name', ['postId' => $post['id']])`. This is a **confirmed-safe divergence** — do NOT flag `postId` as wrong; it is required by the array shape. Related: [[project_worthit-skip-scope-safe.md]].
