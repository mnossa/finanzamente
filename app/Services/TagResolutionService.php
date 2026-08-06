<?php

namespace App\Services;

use App\Models\Tag;

class TagResolutionService
{
    /**
     * @param  array<int|string>  $tagIds
     * @param  array<int, string>  $newTagNames
     * @return array<int, int>
     */
    public function resolve(array $tagIds, array $newTagNames, int $householdId, int $userId): array
    {
        $resolvedIds = Tag::forUser($userId, $householdId)
            ->whereIn('id', $tagIds)
            ->pluck('id')
            ->all();

        foreach ($newTagNames as $tagName) {
            $tag = Tag::findByNameForHousehold($tagName, $householdId, $userId)
                ?? Tag::create([
                    'household_id' => $householdId,
                    'user_id' => $userId,
                    'name' => $tagName,
                    'color' => '#6366f1',
                ]);
            $resolvedIds[] = $tag->id;
        }

        return array_values(array_unique(array_map('intval', $resolvedIds)));
    }
}
