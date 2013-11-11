<?php
namespace ConstructionsIncongrues\Incongrupack\FFMpeg\Format\Audio;

use FFMpeg\Format\Audio\DefaultAudio as BaseDefaultAudio;

abstract class DefaultAudio extends BaseDefaultAudio
{
    protected $extraParams = array();

    public function getExtraParams()
    {
        return $this->extraParams;
    }

    public function setExtraParams(array $params)
    {
        $this->extraParams = array_merge($this->extraParams, $params);
    }
}
