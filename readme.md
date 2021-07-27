# 1.Summary

This project is a javascript bytecode decoder for mozilla spider-monkey version 34.

This version may decompile jsc file compile by cocos-2dx.

It would not work for different version of Mozilla spider-monkey (without shell of course), for its opcode defined different for each version.

Maybe no longer update, but still a good example to understand how javascript virtual machine work (though different engine has different implement).

Is this project can just decompile "34" version only and why?

Well, the truth is may decompile near 34 version before bytecode file structure change.

Js engine may change the file struct for support new language feather, performance optimization, code refactoring, ...

But think of most change is just to add ```section```(concept come of executable binary file) and add ```operation``` instead of change struct.

So this project just try to decompile without check magic code. At least, scan.php will work at most time.

# 2.Usage

## 2.1.Install PHP and Composer

If you are familiar with php, you can skip this part.

install php7.0 (still work in php7.4)
```
# ubuntu
$ sudo apt install php7.0

# mac
$ brew install php7.0

# windows
# just google an binary one
```
install composer
>see https://getcomposer.org/download/

install this project
```
$ cd path/to/project
# no dependences, just auto generate the autoload
$ composer install
```

## 2.2.decompile *.jsc file

```
$ cd /path/to/this/project
$ php run.php /path/to/your.jsc > /path/to/decompile.txt
#if this didn't work, you can also try below command to get the bitcode
$ php scan.php /path/to/your.jsc > /path/to/scan.txt
```

## 2.3. print more info with scan.php

Just remove the slashes in scan.php

# 3. How to guess the bytecode version

| magic code  |  version  |
|    ---      |    ---    |
| 2C C0 73 B9 |     33    |
| 28 C0 73 B9 |     34    |
| 25 C0 73 B9 |     35    |
| 04 C0 73 B9 |     36    |
| FC BF 73 B9 |     37    |
| F4 BF 73 B9 |     38    |
| D1 BF 73 B9 |     39    |
| C3 BF 73 B9 |     40    |
| B7 BF 73 B9 |     41    |
| B3 BF 73 B9 |     42    |
| AB BF 73 B9 |     43    |
| A0 BF 73 B9 |     44    |
| 95 BF 73 B9 |     45    |
| 88 BF 73 B9 |     46    |
| 81 BF 73 B9 |     47    |

Yes, change happens >= 48.
bytecodeVer(int) change to buildId(string).
And buildId is very like an useragent of browser.

# 4.Besides

This project is not complete yet.

- A Fatal Bug was found when decompile with a deep context

Decompile result is not a runable file.
Some local variables are auto generate, for the compiler discards local variables.

