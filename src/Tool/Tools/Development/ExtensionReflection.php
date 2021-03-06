<?php

namespace Imi\Tool\Tools\Development;

use Imi\Bean\ReflectionUtil;
use Imi\Util\File;

class ExtensionReflection
{
    /**
     * ReflectionExtension.
     *
     * @var \ReflectionExtension
     */
    private $ref;

    /**
     * 保存路径.
     *
     * @var string
     */
    private $savePath;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->ref = new \ReflectionExtension($name);
    }

    /**
     * 保存.
     *
     * @param string $path
     *
     * @return void
     */
    public function save($path)
    {
        $this->savePath = $path;
        if (!is_dir($path))
        {
            mkdir($path, 0777, true);
        }
        $this->generateConsts();
        $this->generateFunctions();
        $this->generateClasses();
    }

    /**
     * 生成常量.
     *
     * @return void
     */
    private function generateConsts()
    {
        $result = '<?php' . \PHP_EOL;
        foreach ($this->ref->getConstants() as $name => $value)
        {
            $value = var_export($value, true);
            $result .= <<<CODE
define('{$name}', {$value});

CODE;
        }
        File::putContents($this->savePath . '/consts.php', $result);
    }

    /**
     * 生成函数.
     *
     * @return void
     */
    private function generateFunctions()
    {
        $result = '<?php' . \PHP_EOL;
        foreach ($this->ref->getFunctions() as $function)
        {
            $args = [];
            $comments = [];
            foreach ($function->getParameters() as $param)
            {
                // 方法参数定义
                $args[] = $this->getMethodParamDefine($param);
                $type = $param->getType();
                $comments[] = '@var ' . ($type ? ReflectionUtil::getTypeComments($type) : 'mixed') . ' $' . $param->name;
            }
            $return = $function->getReturnType();
            if (null !== $return)
            {
                $comments[] = '@return ' . ReflectionUtil::getTypeComments($return);
            }
            $args = implode(', ', $args);
            if ([] === $comments)
            {
                $comment = '';
            }
            else
            {
                $comment = implode(\PHP_EOL . ' * ', $comments);
                $comment = <<<COMMENT

/**
 * {$comment}
 */
COMMENT;
            }
            $result .= <<<CODE
{$comment}
function {$function->name}({$args}){}

CODE;
        }
        File::putContents($this->savePath . '/functions.php', $result);
    }

    /**
     * 生成类、接口、trait.
     *
     * @return void
     */
    private function generateClasses()
    {
        foreach ($this->ref->getClasses() as $class)
        {
            if ($class->isInterface())
            {
                $this->generateInterface($class);
            }
            elseif ($class->isTrait())
            {
                $this->generateTrait($class);
            }
            else
            {
                $this->generateClass($class);
            }
        }
    }

    /**
     * 获取方法参数定义模版.
     *
     * @param \ReflectionParameter $param
     *
     * @return string
     */
    private static function getMethodParamDefine(\ReflectionParameter $param)
    {
        $result = '';
        // 类型
        $paramType = $param->getType();
        if ($paramType)
        {
            $paramType = ReflectionUtil::getTypeCode($paramType, $param->getDeclaringClass()->getName());
        }
        $result .= null === $paramType ? '' : ((string) $paramType . ' ');
        if ($param->isPassedByReference())
        {
            // 引用传参
            $result .= '&';
        }
        elseif ($param->isVariadic())
        {
            // 可变参数...
            $result .= '...';
        }
        // $参数名
        $result .= '$' . $param->name;
        // 默认值
        if ($param->isDefaultValueAvailable())
        {
            $result .= ' = ' . var_export($param->getDefaultValue(), true);
        }

        return $result;
    }

    /**
     * 生成类常量.
     *
     * @param \ReflectionClass $class
     *
     * @return string
     */
    private function getClassConsts($class)
    {
        $result = '';
        foreach ($class->getConstants() as $name => $value)
        {
            $value = var_export($value, true);
            $result .= <<<CODE

    const {$name} = $value;

CODE;
        }

        return $result;
    }

    /**
     * 生成类方法.
     *
     * @param \ReflectionClass $class
     *
     * @return string
     */
    private function getClassMethods($class)
    {
        $result = '';

        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method)
        {
            $args = [];
            $comments = [];
            $methodClassName = $method->getDeclaringClass()->getName();
            foreach ($method->getParameters() as $param)
            {
                // 方法参数定义
                $args[] = $this->getMethodParamDefine($param);
                $type = $param->getType();
                $comments[] = '@var ' . ($type ? ReflectionUtil::getTypeComments($type, $methodClassName) : 'mixed') . ' $' . $param->name;
            }
            $return = $method->getReturnType();
            if (null !== $return)
            {
                $comments[] = '@return ' . ReflectionUtil::getTypeComments($return, $methodClassName);
            }
            $args = implode(', ', $args);
            if ([] === $comments)
            {
                $comment = '';
            }
            else
            {
                $comment = implode(\PHP_EOL . '     * ', $comments);
                $comment = <<<COMMENT

    /**
     * {$comment}
     */
COMMENT;
            }
            if ($method->isStatic())
            {
                $static = ' static';
            }
            else
            {
                $static = '';
            }
            $result .= <<<CODE
{$comment}
    public{$static} function {$method->name}({$args}){}

CODE;
        }

        return $result;
    }

    /**
     * 生成类属性.
     *
     * @param \ReflectionClass $class
     *
     * @return string
     */
    public function getClassProperties($class)
    {
        $result = '';
        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property)
        {
            $static = $property->isStatic() ? ' static' : '';
            $name = $property->name;
            $result .= <<<CODE
    public{$static} \${$name};

CODE;
        }

        return $result;
    }

    /**
     * 生成接口.
     *
     * @param \ReflectionClass $class
     *
     * @return void
     */
    private function generateInterface($class)
    {
        $consts = $this->getClassConsts($class);
        $methods = $this->getClassMethods($class);

        $result = '<?php' . \PHP_EOL;

        $className = $class->getShortName();
        $namespace = $class->getNamespaceName();
        if ('' !== $namespace)
        {
            $namespace = 'namespace ' . $namespace . ';';
        }
        $result .= <<<CODE
{$namespace}

interface {$className}
{
{$consts}{$methods}
}

CODE;
        File::putContents($this->savePath . '/interfaces/' . str_replace('\\', '/', $class->getNamespaceName()) . '/' . $class->getShortName() . '.php', $result);
    }

    /**
     * 生成trait.
     *
     * @param \ReflectionClass $class
     *
     * @return void
     */
    private function generateTrait($class)
    {
        $consts = $this->getClassConsts($class);
        $methods = $this->getClassMethods($class);
        $properties = $this->getClassProperties($class);

        $result = '<?php' . \PHP_EOL;

        $className = $class->getShortName();
        $namespace = $class->getNamespaceName();
        if ('' !== $namespace)
        {
            $namespace = 'namespace ' . $namespace . ';';
        }
        $result .= <<<CODE
{$namespace}

trait {$className}
{
{$consts}{$properties}{$methods}
}

CODE;
        File::putContents($this->savePath . '/traits/' . str_replace('\\', '/', $class->getNamespaceName()) . '/' . $class->getShortName() . '.php', $result);
    }

    /**
     * 生成类.
     *
     * @param \ReflectionClass $class
     *
     * @return void
     */
    private function generateClass($class)
    {
        $consts = $this->getClassConsts($class);
        $methods = $this->getClassMethods($class);
        $properties = $this->getClassProperties($class);

        $result = '<?php' . \PHP_EOL;

        $className = $class->getShortName();
        $namespace = $class->getNamespaceName();
        if ('' !== $namespace)
        {
            $namespace = 'namespace ' . $namespace . ';';
        }
        $result .= <<<CODE
{$namespace}

class {$className}
{
{$consts}{$properties}{$methods}
}

CODE;
        File::putContents($this->savePath . '/classes/' . str_replace('\\', '/', $class->getNamespaceName()) . '/' . $class->getShortName() . '.php', $result);
    }
}
