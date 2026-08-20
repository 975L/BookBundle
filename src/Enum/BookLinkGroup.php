<?php

namespace c975L\BookBundle\Enum;

// What a link leads to, which is the card a book's page prints it in - the stores together, the podcasts together
enum BookLinkGroup: string
{
    case Epub = 'epub';
    case Audio = 'audio';
    case Podcast = 'podcast';
    case Video = 'video';
}
