<?php
namespace ConstructionsIncongrues\Incongrupack\FFMpeg\Format\Audio;

/**
 * The Vorbis audio format
 */
class Vorbis extends DefaultAudio
{
    public function __construct()
    {
        $this->audioCodec = 'vorbis';
        $this->setExtraParams(array('-strict', '-2'));
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableAudioCodecs()
    {
        return array('vorbis');
    }
}
