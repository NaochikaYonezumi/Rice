<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserChatHide extends Model
{
    protected $fillable = ['user_id', 'hidable_type', 'hidable_id'];

    public const TYPE_ROOM = 'room';
    public const TYPE_THREAD = 'thread';

    /**
     * ユーザーが非表示にしている (type => [id, ...]) を返す
     */
    public static function getHiddenIdsByType(int $userId): array
    {
        $rows = static::where('user_id', $userId)->get(['hidable_type', 'hidable_id']);
        $map = [self::TYPE_ROOM => [], self::TYPE_THREAD => []];
        foreach ($rows as $r) {
            $map[$r->hidable_type][] = (int) $r->hidable_id;
        }
        return $map;
    }

    /**
     * 非表示登録した時刻まで含めて返す (type => [id => Carbon createdAt])
     * 「非表示にした後に新着メール/チャットが来たら再表示」判定で使う。
     */
    public static function getHiddenMapByType(int $userId): array
    {
        $rows = static::where('user_id', $userId)->get(['hidable_type', 'hidable_id', 'created_at']);
        $map = [self::TYPE_ROOM => [], self::TYPE_THREAD => []];
        foreach ($rows as $r) {
            $map[$r->hidable_type][(int) $r->hidable_id] = $r->created_at;
        }
        return $map;
    }
}
