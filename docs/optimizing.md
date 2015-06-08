# Optimizing
## Introduction
PRISM is pretty fast, but there are some common assumptions about scripts:

* You can't possibly make it any faster.
* It's interpretered, so it's already going to be slow.
* Details don't matter, as it's only "scripting" anyway.

None of these are true. The interpretered, in fact, is pretty good at optimizing. But you can greatly increase the speed and efficiency of your plugins by keeping a few rules in mind. Remember - it's more important to minimize instructions than it is to minimize lines of code.
Note that many of these optimizations should be taken in context. An admin command probably doesn't need fine-tuned optimizations. But a timer that executes every 0.1 seconds, or a MCI / NLP packet hook, should definitely be as fast as possible.

## Always Save Results
Observe the example code snippet below:
```php
if ($this->getClientUName($UCID) == 'Scawen')
{
	# Code
}
else if ($this->getClientUName($UCID) == 'Victor')
{
	# Code
}
else if ($this->getClientUName($UCID) == 'Eric')
{
	# Code
}
```
This is a mild example of "cache your results". When the compiler generates assembly for this code, it will (in pseudo code) generate:
```
CALL getClientUName
COMPARE+JUMP
CALL getClientUName
COMPARE+JUMP
CALL getClientUName
COMPARE+JUMP
```
```php
$UName = $this->getClientUName($UCID);
if ($UName == 'Scawen')
{
	# Code
}
else if ($UName == 'Victor')
{
	# Code
}
else if ($UName == 'Eric')
{
	# Code
}
```
Now, the compiler will only generate this:
```
CALL getClientUName
COMPARE+JUMP
COMPARE+JUMP
COMPARE+JUMP
```
If getClientUName were a more expensive operation (it's relatively cheap), we would have recalculated the entire result each branch of the if case, wasting CPU cycles.

Similarly, this type of code is usually not a good idea:
```php
for ($i = 0; $i < strlen($string); $i++)
```
Better code is:
```php
for ($i = 0, $len = strlen(string); $i < $len; ++$i)
```
I can't stress this point enough, it can be devatating to the proformance of your script, the former getting up to and over 600 times SLOWER in the better example.
Similarly, you may not want to put $this->getMaxClients() in a loop about players. While it is a very cheap function call, it can become significant in highly performance-sensitive code.

## Switch instead of If
If you can, you should use switch cases instead of if. This is because for an if statement, the compiler must branch to each consecutive if case. Using the example from above, observe the switch version:
```php
$UName = $this->getClientByUCID($UCID)->UName;
switch ($UName)
{
	case 'Scawen':
		# Code
		break;
	case 'Victor':
		# Code
		break;
	case 'Eric':
		# Code
		break;
}
```
This will generate what's called a "case table". Rather than worm through displaced if tests, the compiler generates a table of possible values. The best case is:
```
JUMP Table[CALL $this->getUserByUCID($UCID)->UName]
```
If your switch cases are listed in perfectly sequential order (that is, skipping no numbers, either ascending or descending), the PHP engine can make the best optimizations. For example, "7,8,9" and "2,1,0" are examples of perfect switch cases. "1,3,4" is not.

## Foreach instead of For
You should use foreach when you are doing a simple iterating over an array.

## While instead of Foreach
You should use while instead of foreach when you intend to modifty data within the iterating array.

## No code is good code
With any programming language, the less code you can write the better. If you can find a single function that would of done what you did with five function calls use that one function.

## Conclusion
Although optimization is important, you should always keep context in mind. Don't replace every single foreach in your plugins with while just because it might be faster. Identify the areas of your plugin where optimization is significant, and tweak from there.