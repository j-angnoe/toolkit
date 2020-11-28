<?php

// A place to put your php functions and classes
function removeIndentation($content) {
    // Also replace docblock * stars
    $content = preg_replace('~^(\s*)(\*)(\s*)~m', '$1 $3', $content);
    $lines = explode("\n", trim($content));

    // determine the average non-zero minimum amount of spaces    
    $nonZeroLines = array_filter(
        array_map(fn($l) => strspn($l, " \t"), $lines),
        fn($n) => $n > 0
    );

    $minimumSpaces = empty($nonZeroLines) ? 0 : min($nonZeroLines);


    // remove this spacing without destroying any non-whitespace characters.
    return join("\n", array_map(fn($l) => preg_replace("~^\s{0,$minimumSpaces}~", '', $l), $lines));
}

function extractBlock($content, $blockType, $blockId = null) {
    $blockType = ltrim($blockType, '@');
    $fn = $blockId ? 'preg_match' : 'preg_match_all';
    $matches = null;
    if ($fn('~@'.$blockType.($blockId ? "\s+$blockId" : "").'[^\n]*\n(.+?)[^\n]*@end'.$blockType.'~s', $content, $matches)) {
        if ($blockId) { 
            return removeIndentation($matches[1]);
        } else {
            // @todo testen, werkt dit?
            return array_map('removeIndentation', $matches[1]);
        }
    }
    if ($blockId) { 
        return false;
    } else { 
        return [];
    }
}