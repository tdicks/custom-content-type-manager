<?php namespace CCTM\Model;

use CCTM\Exceptions\FileNotFoundException;
use CCTM\Exceptions\InvalidAttributesException;
use CCTM\Exceptions\NotFoundException;

/**
 * Class FilebasedModel
 *
 * This base class is an interface for using flat files to store object data, e.g. JSON files.
 * The intended use case is that one directory contains files representing one data type: all
 * JSON files in that directory should have a normalized structure.
 *
 * @package CCTM\Model
 */
class FilebasedModel {

    use \CCTM\Traits\DotNotation;

    protected $dic;

    protected $id;
    protected $ext = 'json'; // without the dot
    protected $pk; // primary key (should be one of the attributes)
    protected $context = 'create'; // create | update
    protected $filesystem;
    protected $validator; // separate from $dic so we don't need to rely a convention to get the exact validator classname

    /**
     * TODO: inputs:  full-path to model, validator [, attributes? ]
     *
     * @param $dic
     * @param $filesystem
     * @param $validator
     */
    public function __construct($dic, $filesystem, $validator)
    {
        $this->dic = $dic;
        $this->filesystem = $filesystem;
        
        // For testing the BaseModel, otherwise set in the child class
        if (empty($this->pk)) {
            $this->pk = $dic['pk'];
        }
    }


    /**
     * Get relative filename within the Flysystem root
     *
     * @param $id
     *
     * @return string
     * @throws NotFoundException
     */
    public function getFilename($id)
    {
        // Avoid directory transversing
        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $id))
        {
            throw new NotFoundException('Invalid resource name');
        }
        return $id.'.'.$this->ext;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * Says whether we are updating an existing item or creating a new one
     */
    public function isNew()
    {
        return ($this->context == 'create');
    }

    public function getItem($id)
    {
        //$this->filesystem->read($id);
        // TODO: permissions: can read?


        if (!$exists = $this->filesystem->has($this->getFilename($id)))
        {
            throw new FileNotFoundException('File not found: '.$this->getFilename($id));
        }

        $this->fromArray((array) $this->dic['JsonDecoder']->decode($this->filesystem->read($this->getFilename($id))));
        $this->context = 'update';
        $this->id = $id;
        return $this;
    }

    // TODO: filters
    public function getCollection(array $filters=array())
    {
        // TODO: cache this?
        $contents = $this->filesystem->listContents('/');

        //return $contents;
        // Sample contents
        //        Array
        //        (
        //            [type] => file
        //            [path] => x.json
        //            [timestamp] => 1432097947
        //            [size] => 23
        //            [dirname] =>
        //            [basename] => x.json
        //            [extension] => json
        //            [filename] => x
        //        )
        $filtered = array();
        foreach ($contents as $i => $c)
        {
            if ($c['extension'] != $this->ext)
            {
                continue;
            }

            $filtered[] = $this->getItem($c['filename']);
        }
        return $filtered;
    }


    public function delete()
    {
       // print $this->getId(); exit;
        return $this->filesystem->delete($this->getFilename($this->id));
        // do action?  Hook related items to this?
    }

    /**
     * This is a file operation.  If the object attributes have not been persisted (i.e. saved to file),
     * then the new copy will not contain them.
     *
     * @param $new_id
     *
     * @throws FileExistsException
     * @throws NotFoundException
     */
    public function duplicate($new_id)
    {
        // Has this file been saved yet?
        // if ($this->isNew())

        if ($exists = $this->filesystem->has($this->getFilename($new_id)))
        {
            throw new FileExistsException('Target file cannot be ovewritten. '.$this->getFilename($new_id));
        }

        // $filesystem->copy('filename.txt', 'duplicate.txt');

        // $copy = $this->getItem($new_id);
        // Update primary key
        // $copy->set($this->pk, $new_id);
        // $copy->save();
        // return $copy
    }

    public function rename($new_id)
    {
        $oldname = $this->getFilename($this->getId());
        $newname = $this->getFilename($new_id);
        // update pk
        // $this->set($this->pk, $new_id);
        // $filesystem->rename($oldname, $newname);
    }

    public function save()
    {
        // Check PK
        $pk = $this->pk; // prepare string
        $this->id = ($this->isNodeSet($this->pk)) ? $this->get($pk) : null;
        if(!$this->id)
        {
            throw new InvalidAttributesException('Missing primary key.');
        }
        // Validate
        if (!$this->dic['Validator']->validate($this->toArray(), $this->context))
        {
            throw new InvalidAttributesException($this->dic['Validator']->getMessages());
        }

        // After validation, mark this as an update
        $this->context = 'update';

        $this->filesystem->put($this->getFilename($this->id), $this->dic['JsonEncoder']->encode($this->data));
    }
}

/*EOF*/