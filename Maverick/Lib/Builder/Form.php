<?php

/**
 * @package Maverick Framework
 * @author  Alec Carpenter
 */

namespace Maverick\Lib;

class Builder_Form {
    /**
     * The name of the form
     *
     * @var string $name
     */
    private $name = '';

    /**
     * The method of the form
     *
     * @var string $method
     */
    private $method = 'post';

    /**
     * The action of the form
     *
     * @var string $action
     */
    private $action = '';

    /**
     * The encoding type of the form
     *
     * @var string | null
     */
    private $encType = null;

    /**
     * The container for the fields
     *
     * @var mixed
     */
    private $formContainer = null;

    /**
     * The tpl for the form
     *
     * @var string | null
     */
    private $tpl = null;

    /**
     * The variables for the tpl for this form
     *
     * @var array
     */
    private $tplVars = array();

    /**
     * The default field template
     *
     * @var string
     */
    private $defaultFieldTpl = '<div>{LABEL}{REQUIRED} {ERROR}<br />{FIELD}<br />{DESCRIPTION}</div>';

    /**
     * Required field identifier
     *
     * @var string
     */
    private $requiredId = '<span style="color:#AA0000;">*</span>';

    /**
     * The fields
     *
     * @var array $fields
     */
    private $fields = array();

    /**
     * Whether or not to have a submission token
     *
     * @var boolean $submissionToken
     */
    private $submissionToken = null;

    /**
     * Sets up the form
     */
    public function __construct() {
        $this->action = '/' . Router::getUri();

        if(!isset($_SESSION)) {
            $this->submissionToken = false;
        }
    }

    /**
     * Sets the name of the form
     *
     * @param string $name
     */
    protected function setName($name) {
        $this->name = $name;

        if(is_null($this->submissionToken)) {
            $this->toggleSubmissionToken();
        }
    }

    /**
     * Get the name of the field
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Sets the method of the form
     *
     * @param string $method
     */
    protected function setMethod($method) {
        $this->method = $method;
    }

    /**
     * Get the method of the field
     *
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * Sets the action of the form
     *
     * @param string $action
     */
    protected function setAction($action) {
        $this->action = $action;
    }

    /**
     * Get the action of the field
     *
     * @return string
     */
    public function getAction() {
        return $this->action;
    }

    /**
     * Enables file uploads
     */
    protected function allowFileUploads() {
        $this->encType = 'multipart/form-data';
    }

    /**
     * Sets the form container
     *
     * @param mixed $container
     */
    protected function setContainer($container) {
        $this->formContainer = $container;
    }

    /**
     * Sets the template for the form
     *
     * @param string $tpl
     */
    protected function setTpl($tpl) {
        $this->tpl = $tpl;
    }

    /**
     * Gets the tpl of the form
     *
     * @return string
     */
    public function getTpl() {
        return $this->tpl;
    }

    /**
     * Sets a tpl variable for this form's tpl
     *
     * @param string $name
     * @param mixed  $value
     */
    public function setTplVariable($name, $value) {
        $this->tplVars[$name] = $value;
    }

    /**
     * Sets multiple tpl variables for this form's tpl
     *
     * @param array $vars
     */
    public function setTplVariables(array $vars) {
        $this->tplVars = array_merge($this->tplVars, $vars);
    }

    /**
     * Changes the required field marker
     *
     * @param string $requiredId
     */
    protected function setRequiredMarker($requiredId) {
        $this->requiredId = $requiredId;
    }

    /**
     * Gets the required field marker if needed
     *
     * @param  boolean $isRequired=true
     * @return string
     */
    public function getRequiredMarker($isRequired=true) {
        if($isRequired) {
            return $this->requiredId;
        }

        return '';
    }

    /**
     * Adds a field to the form
     *
     * @param  string  $type
     * @param  string  $name
     * @return mixed
     */
    public function addField($type, $name) {
        $class = '\Maverick\Lib\Builder_FormField_' . $type;

        if(array_key_exists($name, $this->fields)) {
            throw new \Exception('You cannot add multiple fields with the same name to the same form.');
        }

        $fieldBuilder = new $class($name);
        $fieldBuilder->setForm($this);

        $this->fields[$name] = $fieldBuilder;

        return $fieldBuilder;
    }

    /**
     * Gets the fields
     *
     * @return string
     */
    public function getFields() {
        return $this->fields;
    }

    /**
     * Adds an ID field to help prevent CSRF
     */
    private function addAntiCSRFToken() {
        $token = $this->input->get('formSubmissionToken') ?: \Maverick\Lib\Utility::generateToken(25);

        $_SESSION[$this->name . '_submission_token'] = $token;

        $this->addField('Input_Hidden', 'formSubmissionToken')
            ->value($token);
    }

    /**
     * Toggle the submission token feature
     */
    public function toggleSubmissionToken() {
        if(isset($_SESSION)) {
            $this->submissionToken = (is_null($this->submissionToken) || !$this->submissionToken) ? true : false;
        }
    }

    /**
     * Says whether or not the submission token feature is enabled
     */
    public function submissionTokenEnabled() {
        if($this->name) {
            return $this->submissionToken;
        }

        return false;
    }

    /**
     * Adds a hidden check field to the form
     */
    private function addCheckField() {
        $this->addField('Input_Hidden', $this->name . '_submit')
            ->value('submitted');
    }

    /**
     * Renders the form for the controller
     *
     * @throws \Exception
     * @return string
     */
    public function render() {
        if(!count($this->fields)) {
            throw new \Exception('No fields have been added to the form');
        }

        if(!$this->name) {
            throw new \Exception('No name was set for the form');
        }

        if($this->submissionTokenEnabled()) {
            $this->addAntiCSRFToken();
        }

        $this->addCheckField();

        $formFields   = array();
        $formContent  = '';
        $hiddenFields = '';

        foreach($this->fields as $name => $f) {
            if($value = $f->getValue()) {
                if($f->isAutoFill()) {
                    $f->value($value);
                }
            }

            if(!$f->isHidden()) {
                if(!is_null($this->tpl)) {
                    $formFields[$name] = $f;
                } else {
                    if($tpl = $f->getTpl()) {
                        $formContent .= $f->render();
                    } else {
                        $placeholders = array('{LABEL}'       => $f->getLabel(),
                                              '{REQUIRED}'    => $this->getRequiredMarker($f->isRequired()),
                                              '{ERROR}'       => $f->getError(),
                                              '{FIELD}'       => $f->render(),
                                              '{DESCRIPTION}' => $f->getDescription());

                        $formContent .= str_replace(array_keys($placeholders), array_values($placeholders), $this->defaultFieldTpl);
                    }
                }
            } else {
                $hiddenFields .= $f->render($this->name);
            }
        }

        $form = new Builder_Tag('form');
        $form->addAttributes(array('name'   => $this->name,
                                   'method' => $this->method,
                                   'action' => $this->action));

        if($this->encType) {
            $form->addAttribute('enc-type', $this->encType);
        }

        if(!is_null($this->tpl)) {
            $vars = array_merge($this->tplVars, array('form'   => $this,
                                                      'fields' => $formFields));

            $renderedForm = \Maverick\Lib\Output::getTplEngine()->getTemplate($this->tpl, $vars);
        } else {
            $container = $this->formContainer;

            if(is_null($container)) {
                $container = new Builder_Tag('div');
            }

            $renderedForm = $container->addContent($formContent)->render();
        }

        $form->addContent($renderedForm . $hiddenFields);

        return $form->render();
    }
}