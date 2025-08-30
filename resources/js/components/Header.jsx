import React, { useState, useEffect } from 'react';

// Check if user is authenticated
const isAuthenticated = () => {
    return document.querySelector('meta[name="user-authenticated"]')?.getAttribute('content') === 'true';
};

const Header = () => {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [dropdownStates, setDropdownStates] = useState({
        documents: false,
        images: false,
        audio: false,
        video: false
    });
    const [mobileDropdownStates, setMobileDropdownStates] = useState({
        documents: false,
        images: false,
        audio: false,
        video: false
    });

    const toggleDropdown = (category) => {
        setDropdownStates(prev => ({
            ...prev,
            [category]: !prev[category]
        }));
    };

    const toggleMobileDropdown = (category) => {
        setMobileDropdownStates(prev => ({
            ...prev,
            [category]: !prev[category]
        }));
    };

    const closeAllDropdowns = () => {
        setDropdownStates({
            documents: false,
            images: false,
            audio: false,
            video: false
        });
    };

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (!event.target.closest('.dropdown')) {
                closeAllDropdowns();
            }
        };

        document.addEventListener('click', handleClickOutside);
        return () => document.removeEventListener('click', handleClickOutside);
    }, []);

    const getColorClasses = (color) => {
        const colorMap = {
            blue: {
                text: 'text-blue-600',
                bg: 'bg-blue-500',
                hover: 'hover:text-blue-600',
                hoverBg: 'hover:bg-blue-50',
                dot: 'bg-blue-400'
            },
            green: {
                text: 'text-green-600',
                bg: 'bg-green-500',
                hover: 'hover:text-green-600',
                hoverBg: 'hover:bg-green-50',
                dot: 'bg-green-400'
            },
            purple: {
                text: 'text-purple-600',
                bg: 'bg-purple-500',
                hover: 'hover:text-purple-600',
                hoverBg: 'hover:bg-purple-50',
                dot: 'bg-purple-400'
            },
            red: {
                text: 'text-red-600',
                bg: 'bg-red-500',
                hover: 'hover:text-red-600',
                hoverBg: 'hover:bg-red-50',
                dot: 'bg-red-400'
            }
        };
        return colorMap[color];
    };

    const DropdownMenu = ({ category, isOpen, onToggle, color, items }) => {
        const colorClasses = getColorClasses(color);
        
        return (
            <div className="relative dropdown">
                <button 
                    className={`text-gray-700 ${colorClasses.hover} px-4 py-2 text-sm font-medium flex items-center transition-colors duration-200 rounded-md ${colorClasses.hoverBg}`}
                    onClick={() => onToggle(category, !isOpen)}
                >
                    {category.charAt(0).toUpperCase() + category.slice(1)}
                    <svg className={`ml-2 h-4 w-4 transform transition-transform duration-200 ${isOpen ? 'rotate-180' : 'rotate-0'}`} 
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div 
                    className={`absolute left-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50 transition-all duration-200 ${
                        isOpen 
                            ? 'opacity-100 visible translate-y-0' 
                            : 'opacity-0 invisible translate-y-2'
                    }`}
                >
                    <div className={`p-2 ${colorClasses.bg} rounded-t-lg`}>
                        <h3 className="text-white font-medium text-sm px-3 py-1">
                            {category.charAt(0).toUpperCase() + category.slice(1)} Tools
                        </h3>
                    </div>
                    <div className="p-3">
                        <ul className="space-y-1">
                            {items.map((item, index) => (
                                <li key={index}>
                                    <a 
                                        href="/register" 
                                        className={`flex items-center text-sm text-gray-700 ${colorClasses.hover} transition-colors duration-200 p-2 rounded-md ${colorClasses.hoverBg}`}
                                    >
                                        <div className={`w-2 h-2 rounded-full ${colorClasses.dot} mr-3`}></div>
                                        {item}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </div>
        );
    };

    // Mobile Dropdown Component
    const MobileDropdownMenu = ({ category, isOpen, onToggle, color, items }) => {
        const colorClasses = getColorClasses(color);
        
        return (
            <div className="border-b border-gray-100">
                <button 
                    className={`w-full text-left px-3 py-3 text-base font-medium text-gray-700 ${colorClasses.hover} flex items-center justify-between transition-colors duration-200`}
                    onClick={() => onToggle(category)}
                >
                    {category.charAt(0).toUpperCase() + category.slice(1)} Tools
                    <svg className={`h-5 w-5 transform transition-transform duration-200 ${isOpen ? 'rotate-180' : 'rotate-0'}`} 
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div className={`overflow-hidden transition-all duration-300 ${
                    isOpen ? 'max-h-96' : 'max-h-0'
                }`}>
                    <div className="px-6 pb-3">
                        <ul className="space-y-1">
                            {items.map((item, index) => (
                                <li key={index}>
                                    <a 
                                        href="/register" 
                                        className={`flex items-center text-sm text-gray-600 ${colorClasses.hover} transition-colors duration-200 p-2 rounded-md ${colorClasses.hoverBg}`}
                                    >
                                        <div className={`w-2 h-2 rounded-full ${colorClasses.dot} mr-3`}></div>
                                        {item}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </div>
        );
    };

    const handleDropdownToggle = (category, state) => {
        setDropdownStates(prev => ({
            documents: false,
            images: false,
            audio: false,
            video: false,
            [category]: state
        }));
    };

    const menuItems = {
        documents: ['Word to PDF', 'PDF to Word', 'Excel to PDF', 'Compress PDF', 'Merge PDF', 'Split PDF'],
        images: ['JPG to PNG', 'PNG to JPG', 'WebP Converter', 'Image Resize', 'Image Compress', 'Crop Image'],
        audio: ['MP3 to WAV', 'WAV to MP3', 'AAC to MP3', 'FLAC to MP3', 'Audio Trim', 'Normalize Volume'],
        video: ['MP4 to AVI', 'AVI to MP4', 'MOV to MP4', 'Video Compress', 'Extract Audio', 'Video Trim']
    };

    return (
        <header className="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between items-center h-16">
                    {/* Logo */}
                    <div className="flex-shrink-0">
                        <h1 className="text-2xl font-bold text-blue-600 hover:text-blue-700 transition-colors duration-200 cursor-pointer">
                            FlexiConvert
                        </h1>
                    </div>

                    {/* Desktop Navigation */}
                    <nav className="hidden md:flex items-center space-x-1">
                        <DropdownMenu 
                            category="documents" 
                            isOpen={dropdownStates.documents}
                            onToggle={handleDropdownToggle}
                            color="blue"
                            items={menuItems.documents}
                        />
                        <DropdownMenu 
                            category="images" 
                            isOpen={dropdownStates.images}
                            onToggle={handleDropdownToggle}
                            color="green"
                            items={menuItems.images}
                        />
                        <DropdownMenu 
                            category="audio" 
                            isOpen={dropdownStates.audio}
                            onToggle={handleDropdownToggle}
                            color="purple"
                            items={menuItems.audio}
                        />
                        <DropdownMenu 
                            category="video" 
                            isOpen={dropdownStates.video}
                            onToggle={handleDropdownToggle}
                            color="red"
                            items={menuItems.video}
                        />
                    </nav>

                    {/* Auth Links */}
                    <div className="hidden md:flex items-center space-x-4">
                        {isAuthenticated() ? (
                            <a href="/dashboard" className="bg-blue-600 text-white px-6 py-2 rounded-full text-sm font-medium hover:bg-blue-700 transition-all duration-200 hover:scale-105 transform">
                                Dashboard
                            </a>
                        ) : (
                            <>
                                <a href="/login" className="text-gray-700 hover:text-blue-600 text-sm font-medium transition-colors duration-200">
                                    Login
                                </a>
                                <a href="/register" className="bg-blue-600 text-white px-6 py-2 rounded-full text-sm font-medium hover:bg-blue-700 transition-all duration-200 hover:scale-105 transform">
                                    Sign up free
                                </a>
                            </>
                        )}
                    </div>

                    {/* Mobile menu button */}
                    <div className="md:hidden">
                        <button 
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="text-gray-700 hover:text-blue-600 transition-colors duration-200"
                        >
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path 
                                    className={`transition-all duration-300 ${mobileMenuOpen ? 'opacity-0' : 'opacity-100'}`}
                                    strokeLinecap="round" 
                                    strokeLinejoin="round" 
                                    strokeWidth="2" 
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path 
                                    className={`transition-all duration-300 ${mobileMenuOpen ? 'opacity-100' : 'opacity-0'}`}
                                    strokeLinecap="round" 
                                    strokeLinejoin="round" 
                                    strokeWidth="2" 
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>

                {/* Mobile Navigation - UPDATED */}
                <div className={`md:hidden transition-all duration-300 ease-in-out ${
                    mobileMenuOpen 
                        ? 'max-h-screen opacity-100' 
                        : 'max-h-0 opacity-0 overflow-hidden'
                }`}>
                    <div className="px-2 pt-2 pb-3 bg-white border-t border-gray-200">
                        {/* Mobile Tools Navigation */}
                        <div className="space-y-1 mb-4">
                            <MobileDropdownMenu 
                                category="documents" 
                                isOpen={mobileDropdownStates.documents}
                                onToggle={toggleMobileDropdown}
                                color="blue"
                                items={menuItems.documents}
                            />
                            <MobileDropdownMenu 
                                category="images" 
                                isOpen={mobileDropdownStates.images}
                                onToggle={toggleMobileDropdown}
                                color="green"
                                items={menuItems.images}
                            />
                            <MobileDropdownMenu 
                                category="audio" 
                                isOpen={mobileDropdownStates.audio}
                                onToggle={toggleMobileDropdown}
                                color="purple"
                                items={menuItems.audio}
                            />
                            <MobileDropdownMenu 
                                category="video" 
                                isOpen={mobileDropdownStates.video}
                                onToggle={toggleMobileDropdown}
                                color="red"
                                items={menuItems.video}
                            />
                        </div>

                        {/* Mobile Auth Links */}
                        <div className="border-t border-gray-200 pt-3 space-y-1">
                            {isAuthenticated() ? (
                                <a href="/dashboard" className="text-gray-700 hover:text-blue-600 block px-3 py-2 text-base font-medium transition-colors duration-200">
                                    Dashboard
                                </a>
                            ) : (
                                <>
                                    <a href="/login" className="text-gray-700 hover:text-blue-600 block px-3 py-2 text-base font-medium transition-colors duration-200">
                                        Login
                                    </a>
                                    <a href="/register" className="bg-blue-600 text-white block px-3 py-2 text-base font-medium rounded-md mx-3 text-center hover:bg-blue-700 transition-colors duration-200">
                                        Sign up free
                                    </a>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </header>
    );
};

export default Header;