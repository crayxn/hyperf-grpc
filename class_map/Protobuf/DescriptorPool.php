<?php

// Protocol Buffers - Google's data interchange format
// Copyright 2008 Google Inc.  All rights reserved.
// https://developers.google.com/protocol-buffers/
//
// Redistribution and use in source and binary forms, with or without
// modification, are permitted provided that the following conditions are
// met:
//
//     * Redistributions of source code must retain the above copyright
// notice, this list of conditions and the following disclaimer.
//     * Redistributions in binary form must reproduce the above
// copyright notice, this list of conditions and the following disclaimer
// in the documentation and/or other materials provided with the
// distribution.
//     * Neither the name of Google Inc. nor the names of its
// contributors may be used to endorse or promote products derived from
// this software without specific prior written permission.
//
// THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
// "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
// LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
// A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
// OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
// SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
// LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
// DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
// THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
// (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
// OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

namespace Google\Protobuf\Internal;

use Exception;

class DescriptorPool
{
    private static self $pool;
    // Map from message names to sub-maps, which are maps from field numbers to
    // field descriptors.
    private array $class_to_desc = [];
    private array $class_to_enum_desc = [];
    private array $proto_to_class = [];

    // content pool
    private array $proto_to_content = [];
    private array $class_to_proto = [];

    public static function getGeneratedPool(): self
    {
        if (!isset(self::$pool)) {
            self::$pool = new DescriptorPool();
        }
        return self::$pool;
    }

    /**
     * @param $data
     * @param bool $use_nested
     * @return void
     * @throws Exception
     */
    public function internalAddGeneratedFile($data, bool $use_nested = false): void
    {
        $files = new FileDescriptorSet();
        $files->mergeFromString($data);

        /**
         * @var FileDescriptorProto $file_proto
         */
        foreach ($files->getFile() as $file_proto) {

            // add content
            $this->addContent($file_proto, $data);

            $file = FileDescriptor::buildFromProto($file_proto);
            foreach ($file->getMessageType() as $desc) {
                $this->addDescriptor($desc);
            }
            unset($desc);

            foreach ($file->getEnumType() as $desc) {
                $this->addEnumDescriptor($desc);
            }
            unset($desc);

            foreach ($file->getMessageType() as $desc) {
                $this->crossLink($desc);
            }
            unset($desc);
        }
    }

    public function addMessage($name, $klass): \Google\Protobuf\Internal\MessageBuilderContext
    {
        return new MessageBuilderContext($name, $klass, $this);
    }

    public function addEnum($name, $klass): \Google\Protobuf\Internal\EnumBuilderContext
    {
        return new EnumBuilderContext($name, $klass, $this);
    }

    public function addDescriptor(Descriptor $descriptor): void
    {
        $this->proto_to_class[$descriptor->getFullName()] =
            $descriptor->getClass();
        $this->class_to_desc[$descriptor->getClass()] = $descriptor;
        $this->class_to_desc[$descriptor->getLegacyClass()] = $descriptor;
        $this->class_to_desc[$descriptor->getPreviouslyUnreservedClass()] = $descriptor;
        foreach ($descriptor->getNestedType() as $nested_type) {
            $this->addDescriptor($nested_type);
        }
        foreach ($descriptor->getEnumType() as $enum_type) {
            $this->addEnumDescriptor($enum_type);
        }
    }

    public function addEnumDescriptor($descriptor): void
    {
        $this->proto_to_class[$descriptor->getFullName()] =
            $descriptor->getClass();
        $this->class_to_enum_desc[$descriptor->getClass()] = $descriptor;
        $this->class_to_enum_desc[$descriptor->getLegacyClass()] = $descriptor;
    }

    public function getDescriptorByClassName($klass)
    {
        return $this->class_to_desc[$klass] ?? null;
    }

    public function getEnumDescriptorByClassName($klass)
    {
        return $this->class_to_enum_desc[$klass] ?? null;
    }

    public function getDescriptorByProtoName($proto)
    {
        if (isset($this->proto_to_class[$proto])) {
            $klass = $this->proto_to_class[$proto];
            return $this->class_to_desc[$klass];
        } else {
            return null;
        }
    }

    public function getEnumDescriptorByProtoName($proto)
    {
        $klass = $this->proto_to_class[$proto];
        return $this->class_to_enum_desc[$klass];
    }

    private function crossLink(Descriptor $desc): void
    {
        /**
         * @var FieldDescriptor $field
         */
        foreach ($desc->getField() as $field) {
            switch ($field->getType()) {
                case GPBType::MESSAGE:
                    $proto = $field->getMessageType();
                    if ($proto[0] == '.') {
                        $proto = substr($proto, 1);
                    }
                    $subdesc = $this->getDescriptorByProtoName($proto);
                    if (is_null($subdesc)) {
                        trigger_error(
                            'proto not added: ' . $proto
                            . " for " . $desc->getFullName(), E_USER_ERROR);
                    }
                    $field->setMessageType($subdesc);
                    break;
                case GPBType::ENUM:
                    $proto = $field->getEnumType();
                    if ($proto[0] == '.') {
                        $proto = substr($proto, 1);
                    }
                    $field->setEnumType(
                        $this->getEnumDescriptorByProtoName($proto));
                    break;
                default:
                    break;
            }
        }
        unset($field);

        foreach ($desc->getNestedType() as $nested_type) {
            $this->crossLink($nested_type);
        }
        unset($nested_type);
    }

    /**
     * @return void
     */
    public function finish(): void
    {
        foreach ($this->class_to_desc as $klass => $desc) {
            $this->crossLink($desc);
        }
        unset($desc);
    }

    /**
     * Get Content By Proto
     * @param string $proto
     * @return string
     */
    public function getContentByProtoName(string $proto): string
    {
        return $this->proto_to_content[$proto] ?? "";
    }

    /**
     * Get Content By Srv Name
     * @param string $server
     * @return string
     */
    public function getContentByServerName(string $server): string
    {
        return $this->proto_to_content[$this->class_to_proto[$server] ?? ""] ?? "";
    }

    /**
     * Add Proto Content
     * @param FileDescriptorProto $file_proto
     * @param string $content
     * @return void
     */
    public function addContent(FileDescriptorProto $file_proto, string $content): void
    {
        // Replace
        $content = str_replace('\\\\', "\\", $content);
        $content = str_replace(substr($content, 1, 3), "", $content);
        // Save
        if (!isset($this->proto_to_content[$file_proto->getName()])) {
            $this->proto_to_content[$file_proto->getName()] = $content;
        }
        /**
         * @var ServiceDescriptorProto $item
         */
        foreach ($file_proto->getService() as $item) {
            $this->class_to_proto["{$file_proto->getPackage()}.{$item->getName()}"] = $file_proto->getName();
        }
    }
}
