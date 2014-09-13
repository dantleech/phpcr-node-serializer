<?php

namespace spec\PHPCR\Shell\Serializer;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class YamlEncoderSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('PHPCR\Shell\Serializer\YamlEncoder');
    }

    function it_should_encode_to_yaml()
    {
        $data = array('foobar' => 'barfoo', 'barfoo' => 'foobar');
        $this->encode($data, 'yaml')->shouldReturn(<<<EOT
foobar: barfoo
barfoo: foobar

EOT
    );
    }

    function is_should_decode_yaml()
    {
        $yaml = <<<EOT
foobar: barfoo
barfoo: foobar
EOT
        ;

        $this->decode($yaml, 'yaml')->shouldReturn(array(
            'foobar' => 'barfoo',
            'barfoo' => 'foobar'
        ));

    }
}
