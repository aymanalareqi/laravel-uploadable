<?php

namespace Alareqi\LaravelUploadable\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait FileUploader
{
    public function getDisk(): string
    {
        return $this->disk ?? config('filesystems.default');
    }

    public function getDirectory(): string
    {
        return $this->directory ?? '/';
    }

    public function getVisibility(): string
    {
        return $this->visibility ?? "public";
    }

    public function getUploadedFileName(UploadedFile $file, self $model): ?string
    {
        return null;
    }
    public function getUploadable(): ?array
    {
        return $this->uploadable ?? [];
    }
    protected static function bootFileUploader(): void
    {
        static::deleting(function (self $model) {
            $softDeletes = in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($model));
            if (($softDeletes && $model->isForceDeleting()) || !$softDeletes) {
                foreach ($model->getUploadable() as $fileField) {
                    if (!isset($model->{$fileField})) {
                        continue;
                    }
                    $model->deleteFile($model->{$fileField});
                }
            }
        });
        static::updating(function (self $model) {

            foreach ($model->getUploadable() as $fileField) {
                if (!isset($model->{$fileField}) || !$model->isDirty($fileField)) {
                    continue;
                }
                if (is_array($model->{$fileField})) {
                    $arr = [];
                    if (isset(request()->all()[$fileField])) {
                        foreach (request()->all()[$fileField] as $file) {
                            $arr[] = $model->uploadFile($file);
                        }
                    }
                    $model->{$fileField} = $arr;
                    $oldFileName = $model->getOriginal($fileField);
                    // get deleted files
                    $deletedFiles = array_diff($oldFileName ?? [], $model->{$fileField},);
                    if (!empty($deletedFiles)) {
                        foreach ($deletedFiles as $file) {
                            $model->deleteFile($file);
                        }
                    }
                } else {
                    $model->{$fileField} = $model->uploadFile($model->{$fileField});
                    $oldFileName = $model->getOriginal($fileField);
                    if ($model->{$fileField} != $oldFileName) {
                        $model->deleteFile($oldFileName);
                    }
                }
            }
        });
        static::creating(function (self $model) {
            foreach ($model->getUploadable() as $fileField) {
                if (!isset($model->{$fileField})) {
                    continue;
                }
                if (is_array($model->{$fileField})) {
                    $arr = [];
                    foreach ($model->{$fileField} as $file) {
                        $arr[] = $model->uploadFile($file);
                    }
                    $model->{$fileField} = $arr;
                } else {
                    $model->{$fileField} = $model->uploadFile($model->{$fileField});
                }
            }
        });
    }

    protected function uploadFile($file, $filename = null, $directory = null, $disk = null): string|false
    {
        if ($file instanceof UploadedFile) {
            $disk = $disk ?? $this->getDisk();
            $directory = $directory ?? $this->getDirectory();
            $filename = $filename ?? $this->getUploadedFileName($file, $this);
            if (!$filename) {
                $name = Storage::disk($disk)->putFile($directory, $file, $this->getVisibility());
            } else {
                $name = Storage::disk($disk)->putFileAs($directory, $file, $filename, $this->getVisibility());
            }
            return $name;
        } else {
            return $file;
        }
    }

    public function deleteFile($filename, $disk = null): bool
    {
        $disk = $disk ?? $this->getDisk();
        return Storage::disk($disk)->delete($filename);
    }
    public function toArray()
    {
        $array = parent::toArray();
        foreach ($this->getUploadable() as $field) {
            if (is_array($array[$field])) {
                $arr = [];
                foreach ($array[$field] as $key => $value) {
                    $arr[] = $this->getUploadableUrl($value);
                }
                $array[$field . '_urls'] = $arr;
            } else {
                $array[$field . '_url'] = $this->getUploadableUrl($array[$field]);
            }
        }
        return $array;
    }
    public function getUploadableUrl($field): ?string
    {
        $url = null;
        if ($field) {
            $url = Storage::disk($this->getDisk())->url($field);
        }
        return $url;
    }

    public function getAttribute($key)
    {
        $attribute = null;
        if (str($key)->contains('_url')) {
            $key = str($key)->replaceLast('_urls', '')
                ->replaceLast('_url', '')->toString();
            if (in_array($key, $this->getUploadable())) {
                $originalAttribute = parent::getAttribute($key);
                if (is_array($originalAttribute)) {
                    $arr = [];
                    foreach ($originalAttribute as $value) {
                        $arr[] = $this->getUploadableUrl($value);
                    }
                    $attribute = $arr;
                } else {
                    $attribute = $this->getUploadableUrl($originalAttribute);
                }
            }
        } else {
            $attribute = parent::getAttribute($key);
        }
        return $attribute;
    }
}
