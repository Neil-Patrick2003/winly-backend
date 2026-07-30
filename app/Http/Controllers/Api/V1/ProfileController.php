<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProfileController extends Controller
{
    /**
     * The disk profile photos are written to.
     */
    protected const DISK = 'public';

    /**
     * The folder within that disk, per kind of photo.
     */
    protected const AVATAR_FOLDER = 'avatars';

    protected const COVER_FOLDER = 'covers';

    /**
     * Show the signed-in user's own profile.
     *
     * The same payload as anybody else's, so one screen renders both; what
     * changes is that the private half of it is filled in.
     */
    public function me(Request $request): ProfileResource
    {
        return $this->profile($request->user(), $request->user());
    }

    /**
     * Show one person's profile.
     *
     * Private accounts are not hidden here. The payload is the header — the
     * name, the photo, the counts — and none of it is the private part; the
     * wins themselves are served elsewhere and are where that decision
     * belongs.
     */
    public function show(Request $request, User $user): ProfileResource
    {
        return $this->profile($user, $request->user());
    }

    /**
     * Edit the signed-in user's profile.
     *
     * A partial edit: fields left out are left alone. Changing the email drops
     * the verification with it, since the new address has not proved anything
     * yet.
     *
     * Sending a photo alongside `remove_avatar` sets the new photo — asking for
     * one is the clearer intent of the two.
     *
     * Multipart bodies cannot be sent as PATCH by most clients, so a photo
     * upload should be POSTed with `_method=PATCH`.
     */
    public function update(UpdateProfileRequest $request): ProfileResource
    {
        $user = $request->user();

        $user->fill($request->profileChanges());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('avatar')) {
            $user->avatar_url = $this->storePhoto($request->file('avatar'), self::AVATAR_FOLDER);
        } elseif ($request->removesAvatar()) {
            $user->avatar_url = null;
        }

        /*
         * The cover follows the avatar exactly, including the rule that sending
         * a photo alongside `remove_cover` sets the photo — asking for one is
         * the clearer of the two intents.
         *
         * Removing it leaves `cover_gradient` alone, so the header falls back to
         * the gradient it had before rather than going blank.
         */
        if ($request->hasFile('cover')) {
            $user->cover_url = $this->storePhoto($request->file('cover'), self::COVER_FOLDER);
        } elseif ($request->removesCover()) {
            $user->cover_url = null;
        }

        $user->save();

        return $this->profile($user, $user);
    }

    /**
     * Build one profile as a given reader sees it.
     *
     * Both follow relations are narrowed to the reader alone: the only two
     * questions the page asks are whether they follow this person and whether
     * this person follows them back, and loading the real lists to answer them
     * would drag every follower of a popular account into memory to look at
     * one row.
     */
    protected function profile(User $user, User $viewer): ProfileResource
    {
        $user->loadActiveStory()->loadCount('posts')->load([
            'followers' => fn (Relation $query) => $query->whereKey($viewer->getKey()),
            'following' => fn (Relation $query) => $query->whereKey($viewer->getKey()),
        ]);

        return new ProfileResource($user);
    }

    /**
     * Store an uploaded profile photo and return the URL it is served from.
     *
     * A failed write is raised rather than shrugged off: `store` answers false
     * when it could not write, and carrying that on into the column would
     * leave the profile pointing at a photo that was never saved.
     *
     * @throws RuntimeException
     */
    protected function storePhoto(UploadedFile $photo, string $folder): string
    {
        $path = $photo->store($folder, self::DISK);

        if ($path === false) {
            throw new RuntimeException('The profile photo could not be stored.');
        }

        return url(Storage::disk(self::DISK)->url($path));
    }
}
