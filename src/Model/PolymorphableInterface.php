<?php
namespace Mongolid\Model;

/**
 * If a model implements the PolymorphableInterface it means that, whenever the
 * model is being "recovered" from the database, it will call the polymorph
 * method and retrieve the object returned from it.
 *
 * See the docblock of the `polymorph` method for more details.
 *
 * @see \Mongolid\Query\ModelAssembler
 */
interface PolymorphableInterface
{
    /**
     * The polymorphic method is something that may be implemented in order to
     * make a model polymorphic. For example: You may have three models within
     * the same collection: `Content`, `ArticleContent` and `VideoContent`.
     * By implementing the polymorph() method it is possible to retrieve an
     * `ArticleContent` or a `VideoContent` object by simply querying
     * within the `Content` model using `first()`, `where()` or `all()`, etc.
     *
     * @example
     *  public function polymorph()
     *  {
     *      if ($this->type === 'video') {
     *          $video = new VideoContent();
     *          $video->fill($this->getDocumentAttributes());
     *
     *          return $video;
     *      }
     *      elseif ($this->type === 'article') {
     *          $article = new ArticleContent();
     *          $article->fill($this->getDocumentAttributes());
     *
     *          return $article;
     *      }
     *
     *      return $this;
     *  }
     *
     * In the example above, if you call Content::first() and the content
     * returned have the key video set, then the object returned will be
     * a VideoContent instead of a Content.
     *
     * @return PolymorphableInterface
     */
    public function polymorph();
}
