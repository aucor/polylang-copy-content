# Polylang Copy Content

**Contributors:** [Teemu Suoranta](https://github.com/TeemuSuoranta), [leemon](https://github.com/theleemon) (this plugin uses bits from  [Sync Attachments for Polylang](https://github.com/theleemon/sync-attachments-for-polylang))


**Tags:** polylang, media, attachments, admin, copy, content


**License:** GPLv2 or later

## Description

**⚠️ Status: In maintenance but not in very active development. [Polylang Pro](https://polylang.pro/) has this feature built-in and actively developed so I recommend supporting the creator of Polylang. ⚠️**

Polylang Copy Content is an add-on for the multilingual WordPress plugin [Polylang](https://wordpress.org/plugins/polylang/). This add-on let's you copy the content and the title WPML style when creating a new translation. All the images and galleries are translated automatically if you use media translations.

Basic feature list:

 * Copy title, content and attachments for new translation
 * Choose the language you want to copy from (make translation from the translated version's editor)
 * Get useful translation markup for captions and title like (es translation) to be overwritten
 * Media translation works for images, captions, galleries and featured image (if you use media translations)
 * Use various filters to modify copied content in code (to be documented and expanded)
 * Translations are done with Polylang's functions, no messing around

## How copying content works?

Copy content basically just copies and pastes all the content first. In this phase this plugin won't need to understand any markup so any shortcodes etc are copied.

On second phase the plugin finds markup with regex, takes out the image IDs, makes or fetches the translation of the image and replaces these inside content. So the plugin can only replace translated images on the markup that this plugin undestands which is the default WordPress markup. If you have plugins that have their own fancy markup, blocks or shortcodes, this plugin copies them but will not process them.

**Why processing images matter?** If you have media translations enabled, you are able to translate captions and alternative texts. These texts live mainly in attachments so while copying the content we need to also replace the attachments to avoid messing up the original images (overwriting the captions etc).

## Copy Content + Classic Editor

Works just fine. Copy content takes care of:

 * Embedded images
 * Galleries
 * Featured image

## Copy Content + Gutenberg

Works okay. Copy conten takes care of:

* Image blocks
* Gallery blocks
* Featured image

Copy content won't process

* Media & Text block (no captions)
* Custom ACF blocks
* Other blocks

If you get "invalid content" error on some block after copying content, you might need to safe draft and refresh the page and it will go away. No idea why as the markup doesn't seem to change.

## Installation

Download and activate. That's it. You will need Polylang, too (d'oh).

**Composer:**
```
$ composer require aucor/polylang-copy-content
```
**With composer.json:**
```
{
  "require": {
    "aucor/polylang-copy-content": "*"
  },
  "extra": {
    "installer-paths": {
      "htdocs/wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
    }
  }
}
```

## Issues and feature whishlist

**Issues:**

 * Translating a link to a featured media page seems to make a broken link sometimes.
 * Adding translation markup (fr translation) might be someting everybody won't like. Maybe I should ~~drop this in future or~~ make it optional.


 **Feature whishlist:**

 * UI to handel wheteher you want to copy things and choose the language
 * Making the plugin itself translatable
 * Ability to copy images to translation after translation has been created (if new images are added to the original version)
 * Undo copying (remove image translations)
 * ~~:star::star: Get integrated to Polylang :star::star:~~ Polylang Pro has similar function that was build unknowingly of this plugin.
