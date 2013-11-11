<?php
namespace ConstructionsIncongrues\Incongrupack\FFMpeg\Format\Audio;

/**
 * The MP3 audio format
 */
class Flac extends DefaultAudio
{
    public function __construct()
    {
        $this->audioCodec = 'flac';
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableAudioCodecs()
    {
        return array('flac');
    }
}
