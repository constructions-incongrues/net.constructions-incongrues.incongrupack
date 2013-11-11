<?php
namespace ConstructionsIncongrues\Incongrupack\FFMpeg\Format\Audio;

/**
 * The MP3 audio format
 */
class Mp3 extends DefaultAudio
{
    public function __construct()
    {
        $this->audioCodec = 'libmp3lame';
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableAudioCodecs()
    {
        return array('libmp3lame');
    }
}
