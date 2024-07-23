<?php

namespace PhpServer\Services\DependencyInjector;

use PhpServer\Attributes\InterfaceIterator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

final class ServiceContainer
{
    private array $serviceNames = [];

    public function __construct()
    {
        $this->setServiceClassNames(__DIR__ . '/../../../src');
    }

    /**
     * Recursively sets the service class names in the given directory.
     *
     * @param string $directory The directory to search for service classes.
     * @return void
     */
    private function setServiceClassNames(string $directory): void
    {
        // Check if the directory exists
        if (is_dir($directory)) {
            // Get an iterator for the directory
            $dir = new \DirectoryIterator($directory);

            // Iterate over each file in the directory
            foreach ($dir as $file) {
                // Skip if it is a dot file
                if ($file->isDot()) {
                    continue;
                }

                // Check if it is a directory
                if ($file->isDir()) {
                    // Recursively set service class names for the subdirectory
                    $this->setServiceClassNames($file->getPathname());
                    continue;
                }

                // Get the real path of the file
                $classPath = $file->getRealPath();

                // Get the class namespace by replacing the src directory with PhpServer
                $classNamespace = preg_replace('/^.*src\//', 'PhpServer\\', $classPath);

                // Replace slashes and .php with \ and empty string, respectively
                $classNamespace = str_replace(['/', '.php'], ['\\', ''], $classNamespace);

                // Skip if it is the current class
                if ($classNamespace === __CLASS__) {
                    continue;
                }

                // Get the reflection class
                $class = new ReflectionClass($classNamespace);

                // Check if the class is instantiable and not already added to the service names
                if ($class->isInstantiable() && ! in_array($classNamespace, $this->serviceNames)) {
                    // Add the class namespace to the service names
                    $this->serviceNames[] = $classNamespace;
                }
            }
        }
    }

    /**
     * Get the list of service names.
     *
     * @return string[] The list of service names.
     */
    public function getServiceNames(): array
    {
        return $this->serviceNames;
    }

    /**
     * Get an instance of the specified service.
     *
     * @param string $serviceName The name of the service to get.
     * @return object The instance of the service.
     * @throws \Exception If the service is not found.
     */
    public function get(string $serviceName): object
    {
        $this->validateServiceExists($serviceName);

        try {
            $reflection = new ReflectionClass($serviceName);
            $constructor = $reflection->getConstructor();

            $attributes = $constructor === null ? [] : $this->getConstructorAttributes($constructor);
            $parameters = $constructor === null ? [] : $constructor->getParameters();

            $instances = $this->getConstructorParameterInstances($serviceName, $parameters, $attributes);

            $instance = $reflection->newInstanceArgs($instances);

            $this->validateInstanceType($instance, $serviceName);
        } catch (\Exception $e) {
            throw new \Exception('Service not found', 0, $e);
        }

        return $instance;
    }

    /**
     * Validate if the specified service exists in the container.
     *
     * @param string $serviceName The name of the service to validate.
     * @return void
     * @throws \Exception If the service is not found.
     */
    private function validateServiceExists(string $serviceName): void
    {
        if (! in_array($serviceName, $this->serviceNames)) {
            throw new \Exception('Service not found');
        }
    }

    /**
     * Get the constructor attributes of the given service.
     *
     * @param ReflectionMethod $constructor The constructor of the service.
     * @return array<ReflectionAttribute> The constructor attributes.
     */
    private function getConstructorAttributes(ReflectionMethod $constructor): array
    {
        return $constructor->getAttributes(InterfaceIterator::class);
    }

    /**
     * Get the constructor parameter instances for the given service.
     *
     * @param string $serviceName The name of the service.
     * @param ReflectionParameter[] $parameters The parameters of the constructor.
     * @param ReflectionAttribute[] $attributes The attributes of the constructor.
     * @return array<string, object|array<string, object>> The instances of the constructor parameters.
     */
    private function getConstructorParameterInstances(
        string $serviceName,
        array $parameters,
        array $attributes
    ): array {
        // Initialize the instances array
        $instances = [];

        // Loop through each parameter
        foreach ($parameters as $parameter) {
            // Get the instance of the parameter
            $instance = $this->getParameterInstance($serviceName, $parameter, $attributes);

            // If the instance has only one value, assign it to the parameter name
            // Otherwise, assign the whole instance array
            $instances[$parameter->getName()] = count($instance) === 1 ? reset($instance) : $instance;
        }

        // Return the instances array
        return $instances;
    }

    /**
     * Get the instance of a parameter in the constructor.
     *
     * @param string $serviceName The name of the service.
     * @param ReflectionParameter $parameter The parameter of the constructor.
     * @param ReflectionAttribute[] $attributes The attributes of the constructor.
     * @return array<string, object> The instance of the parameter.
     */
    private function getParameterInstance(
        string $serviceName,
        ReflectionParameter $parameter,
        array $attributes
    ): array {
        $instances = [];
        // Loop through each attribute
        foreach ($attributes as $attribute) {
            // Check if the attribute matches the parameter
            if ($this->isAttributeMatch($attribute, $parameter)) {
                // Get the interface name of the attribute
                $interfaceName = $this->getInterfaceName($attribute);
                // Get the instances of the interface
                $instances = $this->getInterfaceInstances($serviceName, $interfaceName, $instances);
            }
        }

        // Check if the parameter is a class and not a built-in type
        if ($parameter->hasType() && !$parameter->getType()->isBuiltin()) {
            // Get the class name of the parameter
            $className = $parameter->getType()->getName();
            // Get the instance of the class
            $instances[$parameter->getName()] = $this->getServiceInstance($className);
        } elseif ($parameter->isDefaultValueAvailable()) {
            // Get the default value of the parameter
            $defaultValue = $parameter->getDefaultValue();
            // Add the default value to the instances array
            $instances[$parameter->getName()] = $defaultValue;
        }

        // Return the instances array
        return $instances;
    }

    /**
     * Check if the given attribute matches the parameter.
     *
     * @param ReflectionAttribute $attribute The attribute to check.
     * @param ReflectionParameter $parameter The parameter to check against.
     * @return bool True if the attribute matches the parameter, false otherwise.
     */
    private function isAttributeMatch(ReflectionAttribute $attribute, ReflectionParameter $parameter): bool
    {
        // Get the arguments of the attribute
        $arguments = $attribute->getArguments();

        // Check if the attribute has arguments and the first argument matches the parameter name
        return $arguments !== [] && $arguments[0] === $parameter->getName();
    }

    /**
     * Get the interface name from the given attribute.
     *
     * @param ReflectionAttribute $attribute The attribute containing the interface name.
     * @return string The interface name.
     */
    private function getInterfaceName(ReflectionAttribute $attribute): string
    {
        // Get the arguments of the attribute, specifically the second argument
        // which represents the interface name
        $arguments = $attribute->getArguments();

        // The first argument is the name of the parameter, so the second argument
        // is the interface name
        return $arguments[1];
    }

    /**
     * Get the instances of the interface for the given service.
     *
     * @param string $serviceName The name of the service.
     * @param string $interfaceName The name of the interface.
     * @param array<string, object|array<string, object>> $instances The instances of the service.
     * @return array<string, object|array<string, object>> The instances of the interface.
     */
    private function getInterfaceInstances(
        string $serviceName,
        string $interfaceName,
        array $instances
    ): array {
        // Get the classes implementing the interface
        $implementingClasses = $this->getImplementingClasses($interfaceName);

        // Loop through each implementing class
        foreach ($implementingClasses as $class) {
            // Get the constructor of the implementing class
            $constructor = (new ReflectionClass($class))->getConstructor();

            // Get the constructor instances for the implementing class
            $instances = $this->getConstructorInstances(
                $serviceName,
                $class,
                $constructor,
                $instances
            );
        }

        // Return the instances of the interface
        return $instances;
    }

    /**
     * Get the classes that implement a given interface.
     *
     * @param class-string<object> $interfaceName The name of the interface.
     * @return array<class-string<object>> The classes that implement the interface.
     */
    private function getImplementingClasses(string $interfaceName): array
    {
        // Filter the service names to keep only those that implement the
        // interface and are not interfaces themselves
        return array_filter($this->serviceNames, function (string $service) use ($interfaceName): bool {
            $reflection = new ReflectionClass($service);

            // Check if the class implements the interface and is not an interface itself
            return $reflection->implementsInterface($interfaceName) && ! $reflection->isInterface();
        });
    }

    /**
     * Get the constructor instances of a given class.
     *
     * If the class has a constructor, get the constructor parameter instances.
     * Then, create a new instance of the class and add it to the instances array.
     *
     * @param class-string<object> $serviceName The name of the service.
     * @param class-string<object> $class The name of the class.
     * @param ReflectionMethod|null $constructor The constructor of the class.
     * @param array<string, object|array<string, object>> $instances The array of instances.
     * @return array<string, object|array<string, object>> The array of instances with the new instance added.
     */
    private function getConstructorInstances(
        string $serviceName,
        string $class,
        ?ReflectionMethod $constructor,
        array $instances
    ): array {
        // If the class has a constructor, get the constructor parameter instances
        if ($constructor !== null) {
            $instances = $this->getConstructorParameterInstances(
                $serviceName,
                $constructor->getParameters(),
                []
            );
        }

        // Create a new instance of the class and add it to the instances array
        $instances[$class] = new $class();

        return $instances;
    }

    /**
     * Get an instance of the specified service.
     *
     * @param string $serviceName The name of the service to get.
     * @return object The instance of the service.
     */
    private function getServiceInstance(string $serviceName): object
    {
        // Get an instance of the specified service
        return $this->get($serviceName);
    }

    /**
     * Validate if the instance is of the expected type.
     *
     * @param object $instance The instance to validate.
     * @param string $serviceName The expected type of the instance.
     * @return void
     * @throws \Exception If the instance is not of the expected type.
     */
    private function validateInstanceType(object $instance, string $serviceName): void
    {
        // Check if the instance is of the expected type
        if (! $instance instanceof $serviceName) {
            // Throw an exception if the instance is not of the expected type
            throw new \Exception('Service not found');
        }
    }
}
