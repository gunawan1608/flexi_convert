import React from 'react';

const Footer = () => {
    return (
        <footer className="bg-white border-t border-gray-200">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div className="grid md:grid-cols-2 gap-12">
                    {/* Company Info & Contact */}
                    <div className="space-y-8">
                        <div>
                            <h3 className="text-3xl font-bold text-blue-600 mb-4">
                                FlexiConvert
                            </h3>
                            <p className="text-gray-600 text-lg leading-relaxed max-w-md">
                                Transform your files effortlessly with our professional conversion platform. 
                                Fast, secure, and reliable file processing for all your needs.
                            </p>
                        </div>
                        
                        {/* Contact Options */}
                        <div className="space-y-4">
                            <h4 className="text-xl font-semibold text-gray-900 mb-4">Get in Touch</h4>
                            <div className="flex flex-col space-y-3">
                                <a 
                                    href="mailto:tamagunawan08@gmail.com" 
                                    className="flex items-center space-x-4 text-gray-700 hover:text-blue-600 transition-colors duration-200 group"
                                >
                                    <div className="bg-blue-100 p-3 rounded-lg group-hover:bg-blue-200 transition-colors duration-200">
                                        <svg className="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                                        </svg>
                                    </div>
                                    <span className="font-medium">tamagunawan08@gmail.com</span>
                                </a>
                                
                                <a 
                                    href="https://instagram.com/gm_pratama16" 
                                    target="_blank" 
                                    rel="noopener noreferrer"
                                    className="flex items-center space-x-4 text-gray-700 hover:text-pink-600 transition-colors duration-200 group"
                                >
                                    <div className="bg-pink-100 p-3 rounded-lg group-hover:bg-pink-200 transition-colors duration-200">
                                        <svg className="w-5 h-5 text-pink-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                        </svg>
                                    </div>
                                    <span className="font-medium">@gm_pratama16</span>
                                </a>
                                
                                <a 
                                    href="https://github.com/gunawan1608" 
                                    target="_blank" 
                                    rel="noopener noreferrer"
                                    className="flex items-center space-x-4 text-gray-700 hover:text-gray-900 transition-colors duration-200 group"
                                >
                                    <div className="bg-gray-100 p-3 rounded-lg group-hover:bg-gray-200 transition-colors duration-200">
                                        <svg className="w-5 h-5 text-gray-700" fill="currentColor" viewBox="0 0 20 20">
                                            <path fillRule="evenodd" d="M10 0C4.477 0 0 4.484 0 10.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0110 4.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.203 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.942.359.31.678.921.678 1.856 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0020 10.017C20 4.484 15.522 0 10 0z" clipRule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <span className="font-medium">gunawan1608</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    {/* About Developer */}
                    <div className="space-y-6">
                        <div className="space-y-4 text-center">
                            <h4 className="text-xl font-semibold text-gray-900 mb-4">About Developer</h4>
                            
                            <div className="mb-4">
                                <h5 className="text-lg font-bold text-blue-600 mb-2">Gunawan Madia Pratama</h5>
                                <p className="text-gray-600 font-medium mb-3">Web Developer & Game Developer</p>
                            </div>
                            
                            <p className="text-gray-600 text-sm leading-relaxed mb-4">
                                I am a student at SMKN 1 Jakarta majoring in Software and Game Development Engineering. 
                                This conversion platform is a project I developed to help users transform their files 
                                with an intuitive and modern interface.
                            </p>
                            
                            <p className="text-gray-600 text-sm leading-relaxed mb-4">
                                I have a passion for creating user-friendly technology solutions that can improve 
                                digital workflow efficiency. Through this project, I hope to contribute to making 
                                file conversion more accessible and reliable.
                            </p>
                            
                            <div className="flex flex-wrap gap-2 mb-4 justify-center">
                                <span className="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">PHP</span>
                                <span className="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">JavaScript</span>
                                <span className="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">React</span>
                                <span className="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">HTML5</span>
                                <span className="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">CSS3</span>
                                <span className="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">MySQL</span>
                                <span className="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-medium">Godot</span>
                            </div>
                        </div>
                        
                        <div className="text-center">
                            <a 
                                href="/register" 
                                className="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200 shadow-md hover:shadow-lg"
                            >
                                Get Started Today
                                <svg className="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                {/* Bottom Section */}
                <div className="border-t border-gray-200 mt-12 pt-8">
                    <div className="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                        <p className="text-gray-600 text-sm">
                            © 2025 FlexiConvert. All rights reserved.
                        </p>
                        <div className="flex items-center space-x-6">
                            <span className="text-gray-600 text-sm">Built with Laravel & React</span>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    );
};

export default Footer;