<?php
// ============================================================
// ITEM DESCRIPTION ENHANCER
// Local deterministic copy improvement for admin item creation.
// Keeps facts intact while making auction copy more bidder-friendly.
// ============================================================

function enhanceAuctionDescription($title, $description, $context = []) {
    $title = trim((string)$title);
    $description = normalizeDescriptionText($description);

    if ($title === '') {
        throw new InvalidArgumentException('Item title is required.');
    }

    if (str_word_count($description) < 8) {
        throw new InvalidArgumentException('Add a little more detail first so the app has enough to improve.');
    }

    $sentences = splitDescriptionSentences($description);
    $opening = $sentences[0] ?? $description;
    $included = detectIncludedDetails($sentences);
    $restrictions = detectRestrictionDetails($sentences);
    $supporting = array_values(array_filter(
        array_slice($sentences, 1, 4),
        'isAuctionSellingDetail'
    ));
    $audience = inferBidderAppeal($title, $description);

    $paragraphs = [];
    $paragraphs[] = sprintf(
        'Give bidders a reason to picture themselves winning %s. %s',
        $title,
        ensureSentence($opening)
    );

    $paragraphs[] = sprintf(
        'This item is especially appealing for %s because it feels personal, memorable, and easy to enjoy. %s',
        $audience,
        buildSupportingSentence($supporting, $title)
    );

    if ($included !== '') {
        $paragraphs[] = 'Includes: ' . ensureSentence($included);
    }

    if ($restrictions !== '') {
        $paragraphs[] = 'Please note: ' . ensureSentence($restrictions);
    }

    $imagePrompt = buildAuctionImagePrompt($title, $description);

    return [
        'description' => implode("\n\n", array_filter($paragraphs)),
        'image_prompt' => $imagePrompt,
        'summary' => summarizeForCatalog($title, $description),
    ];
}

function normalizeDescriptionText($text) {
    $text = trim(strip_tags((string)$text));
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\R{3,}/', "\n\n", $text);
    return trim($text);
}

function splitDescriptionSentences($description) {
    $parts = preg_split('/(?<=[.!?])\s+/', $description) ?: [];
    return array_values(array_filter(array_map('trim', $parts)));
}

function ensureSentence($text) {
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }

    return preg_match('/[.!?]$/', $text) ? $text : $text . '.';
}

function buildSupportingSentence($sentences, $title) {
    if (!empty($sentences)) {
        return ensureSentence(implode(' ', $sentences));
    }

    return 'The winning bidder receives a polished experience with enough detail to feel special before the first bid is placed.';
}

function detectIncludedDetails($sentences) {
    foreach ($sentences as $sentence) {
        if (preg_match('/^\s*(?:includes?|package includes|comes with)\s*:?\s*(.+)$/i', $sentence, $matches)) {
            return trim($matches[1]);
        }
    }

    return '';
}

function detectRestrictionDetails($sentences) {
    foreach ($sentences as $sentence) {
        if (isAuctionRestrictionDetail($sentence)) {
            $restriction = preg_replace(
                '/^\s*(?:restrictions?|please note|note|subject to availability)\s*:?\s*/i',
                '',
                trim($sentence)
            );
            return preg_replace('/^\s*(?:and|or|but)\s+/i', '', $restriction);
        }
    }

    return '';
}

function isAuctionSellingDetail($sentence) {
    return !isAuctionIncludedDetail($sentence) && !isAuctionRestrictionDetail($sentence);
}

function isAuctionIncludedDetail($sentence) {
    return preg_match('/^\s*(?:includes?|package includes|comes with)\b/i', $sentence) === 1;
}

function isAuctionRestrictionDetail($sentence) {
    return preg_match('/\b(?:subject to availability|blackout dates?|expires?|valid through|must be booked|not included|travel not included|advance booking|required)\b/i', $sentence) === 1;
}

function inferBidderAppeal($title, $description) {
    $haystack = strtolower($title . ' ' . $description);

    if (preg_match('/dinner|chef|wine|tasting|restaurant|cocktail|brunch/', $haystack)) {
        return 'hosting friends, celebrating a milestone, or creating a night out that feels already taken care of';
    }

    if (preg_match('/trip|weekend|stay|retreat|cabin|hotel|vineyard|travel/', $haystack)) {
        return 'couples, families, or friends who want a getaway with less planning and more anticipation';
    }

    if (preg_match('/spa|wellness|massage|fitness|self-care|relax/', $haystack)) {
        return 'anyone who would love a restorative pause or a thoughtful gift for someone who gives a lot to others';
    }

    if (preg_match('/art|ceramic|jewelry|home|decor|studio|handmade/', $haystack)) {
        return 'bidders who appreciate beautiful objects, local makers, and something lasting to bring home from the event';
    }

    return 'supporters looking for something meaningful, useful, and easy to say yes to';
}

function buildAuctionImagePrompt($title, $description) {
    $cleanDescription = preg_replace('/\s+/', ' ', normalizeDescriptionText($description));
    $cleanDescription = rtrim(safeTextSubstring($cleanDescription, 0, 700), ". \t\n\r\0\x0B") . '.';

    return sprintf(
        'Professional nonprofit gala auction image for "%s". Visual details from item description: %s Warm, donor-trustworthy, polished catalog style, 4:3 landscape, crisp detail, no readable text, no logos, leave clean space for app/PDF title and lot overlay.',
        $title,
        $cleanDescription
    );
}

function summarizeForCatalog($title, $description) {
    $description = normalizeDescriptionText($description);
    $summary = safeTextSubstring($description, 0, 180);
    if (safeTextLength($description) > 180) {
        $summary = preg_replace('/\s+\S*$/', '', $summary) . '...';
    }

    return $title . ': ' . $summary;
}

function safeTextSubstring($text, $start, $length) {
    if (function_exists('mb_substr')) {
        return mb_substr($text, $start, $length);
    }

    return substr($text, $start, $length);
}

function safeTextLength($text) {
    if (function_exists('mb_strlen')) {
        return mb_strlen($text);
    }

    return strlen($text);
}
?>
